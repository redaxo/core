/* Article history: the layer comparing the current article with former versions (requires noUiSlider) */

(function () {

    var DEBUGMODE = false;

    document.addEventListener('rex:ready', function () {
        DEBUGMODE = null !== document.querySelector('.rex-is-debugmode');
    });

    // the buttons of the layer have their own handlers, see bindEventHandlers()
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-history-layer]');
        if (!trigger) {
            return;
        }
        e.preventDefault();
        if ('open' === trigger.dataset.historyLayer) {
            new HistoryLayer('#content-history-layer');
        }
    });


    /**
     * debounce
     * https://davidwalsh.name/javascript-debounce-function
     *
     * @param func
     * @param wait
     * @param immediate
     * @returns {Function}
     */
    function debounce(func, wait, immediate) {
        var timeout;
        return function () {
            var context = this, args = arguments;
            var later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };


    /**
     * debug log helper
     *
     * @type {{log, info, error}}
     */
    var debug = (function () {
        return {
            log: function () {
                var args = Array.prototype.slice.call(arguments);
                (DEBUGMODE) ? console.log.apply(console, args) : false;
            },
            info: function () {
                var args = Array.prototype.slice.call(arguments);
                (DEBUGMODE) ? console.info.apply(console, args) : false;
            },
            error: function () {
                var args = Array.prototype.slice.call(arguments);
                (DEBUGMODE) ? console.error.apply(console, args) : false;
            }
        }
    })();


    /**
     * fetch helper rejecting on HTTP errors
     *
     * @param url
     * @returns {Promise<Response>}
     */
    function request(url) {
        return fetch(url).then(function (response) {
            if (!response.ok) {
                debug.error('request failure: ' + response.status + ' ' + url);
                throw new Error(response.statusText);
            }
            return response;
        });
    }


    /**
     * History layer
     *
     * @constructor
     */
    var HistoryLayer = function (el) {

        // use variables from HTML
        this.articleId = history_article_id;
        this.languageId = history_language_id;
        this.contentSectionId = history_content_section_id;
        this.link = history_article_link;

        // set up environment ojects
        this.dates = {};
        this.gaps = {};
        this.slider = {};

        // set debouncer for frame content change
        this.setFrameContentDebounce = debounce(this.setFrameContent.bind(this), 500);

        // init layer content
        var that = this;
        this.load(this.articleId, this.languageId).then(function (data) {

            // do init stuff
            that.injectToPage(el, data);
            that.initUiElements(el);
            that.setFrameContent(that.currentFrame);
            that.setFrameContent(that.targetFrame);

            // fetch dates from selector element
            that.dates = new Dates({
                dates: that.fetchDatesFromSelector()
            });

            // Go for slider if more than 1 date available
            if (that.dates.getAll().length > 1) {

                // calculate gaps between dates
                that.gaps = new Gaps({
                    dates: that.dates.getAll(),
                    layoutWidth: that.calculateContentWidth(), // layout width used for slider calculation
                    pipsWidth: 2, // pips width (px)
                    pipsGap: 4 // gap width (px)
                });

                // init slider
                that.slider = new Slider({
                    dates: that.dates.getAll(),
                    gaps: that.gaps.getAll(),
                    currentDateText: that.selector.options[0].innerText,
                    containerElement: that.el.querySelector('.history-layer-slider')
                });
                that.sliderSelect = that.slider.noUiSlider;
            }

            // finish init stuff
            that.bindEventHandlers();
            that.updateUIelements();
            that.show();
        });
    };

    HistoryLayer.prototype = {

        /**
         * load article content
         *
         * @param articleId
         * @param languageId
         * @returns {*}
         */
        load: function (articleId, languageId) {
            var url = 'index.php?rex_history_function=layer&history_article_id=' + articleId + '&history_language_id=' + languageId;
            debug.info('load: ' + url);
            return request(url).then(function (response) {
                debug.info('load success');
                return response.text();
            });
        },

        /**
         * calculate content width
         * used for slider calculation later on
         * requires layer to render first
         */
        calculateContentWidth: function () {
            this.el.style.display = 'block';
            var width = this.el.querySelector('.history-layer-layout').clientWidth;
            debug.log('calculate layer content width: ' + width);
            return width;
        },

        /**
         * fetch values from select element
         *
         * @returns {Array}
         */
        fetchDatesFromSelector: function () {
            debug.log('fetch dates from selector');
            var dates = [];
            Array.prototype.forEach.call(this.selector.options, function (option) {
                if (option.value) {
                    dates.push({
                        timestamp: parseInt(option.value, 10),
                        historyDate: option.dataset.historyDate
                    });
                }
                else {
                    dates.push({
                        timestamp: Math.floor(Date.now() / 1000), // current timestamp
                        historyDate: false
                    });
                }
            });
            return dates.reverse(); // reverse, so start with lowest value!
        },

        /**
         * inject element to page
         *
         * @param el
         * @param data
         */
        injectToPage: function (el, data) {
            debug.log('inject to page');
            var existing = document.querySelector(el);
            if (existing) {
                existing.outerHTML = data;
            }
            else {
                document.body.insertAdjacentHTML('beforeend', data);
            }
        },

        /**
         * init UI elements
         * @param el
         */
        initUiElements: function (el) {
            debug.log('init UI elements');
            this.el = document.querySelector(el);
            this.selector_current = this.el.querySelector('#content-history-select-date-1');
            this.selector = this.el.querySelector('#content-history-select-date-2');
            this.selectPrev = this.el.querySelector('[data-history-layer="prev"]');
            this.selectNext = this.el.querySelector('[data-history-layer="next"]');
            this.currentFrame = this.el.querySelector('#content-history-iframe-1');
            this.targetFrame = this.el.querySelector('#content-history-iframe-2');
            this.submit = this.el.querySelector('[data-history-layer="snap"]');
            this.cancel = this.el.querySelector('[data-history-layer="cancel"]');
        },

        /**
         * bind event handlers
         */
        bindEventHandlers: function () {

            var that = this;

            debug.log('bind event handlers');
            this.selector.addEventListener('change', this.onSelect.bind(this));
            this.selectPrev.addEventListener('click', this.onSelectPrev.bind(this));
            this.selectNext.addEventListener('click', this.onSelectNext.bind(this));
            this.submit.addEventListener('click', this.onSubmit.bind(this));
            this.cancel.addEventListener('click', this.onCancel.bind(this));

            if (this.sliderSelect) {
                this.sliderSelect.on('change', this.onSliderSelect.bind(this));
            }
            this.selector_current.addEventListener('change', function () {
                var revision = that.selector_current.value;
                var src = that.link + "?rex_version=" + revision;
                that.currentFrame.src = src;
            });
        },

        /**
         * on selector change
         */
        onSelect: function () {
            debug.log('select ' + this.selector.value);
            this.set(this.dates.getIndexForTimestamp(this.selector.value));
        },

        /**
         * on previous button toggle
         */
        onSelectPrev: function () {
            debug.log('select previous');
            this.setPrev();
        },

        /**
         * on next button toggle
         */
        onSelectNext: function () {
            debug.log('select next');
            this.setNext();
        },

        /**
         * on slider change
         */
        onSliderSelect: function () {
            debug.log('select slider: ' + this.slider.noUiSlider.get());
            this.set(this.dates.getIndexForTimestamp(this.slider.noUiSlider.get()));
        },

        /**
         * on submit button toggle
         */
        onSubmit: function () {
            var that = this;
            var historyDate = that.dates.getCurrent().historyDate;
            debug.log('submit: ' + historyDate);
            this.snapVersion(historyDate).then(function () {
                that.remove();

                // reload redaxo page
                var url = 'index.php?page=content/edit&article_id=' + that.articleId + '&language=' + that.languageId + '&content_section_id=' + that.contentSectionId;
                window.location.href = url;
            });
        },

        /**
         * on cancel button toggle
         */
        onCancel: function () {
            debug.log('cancel');
            this.remove();
        },

        /**
         * update UI elements
         */
        updateUIelements: function () {
            debug.log('update UI elements');
            if (this.dates.getIndex() === (this.dates.getAll().length - 1)) {
                this.selector.value = ''; // option value for newest history date is empty!
            }
            else {
                this.selector.value = this.dates.getCurrent().timestamp;
            }
            this.selectPrev.disabled = this.dates.getPreviousIndex() === false;
            this.selectNext.disabled = this.dates.getNextIndex() === false;

            if (this.sliderSelect) {
                this.sliderSelect.set(this.dates.getCurrent().timestamp);
            }
        },

        /**
         * set to index
         *
         * @param index
         * @param useDebounce boolean
         */
        set: function (index, useDebounce) {
            if (index !== this.dates.getIndex()) {
                this.dates.setIndex(index);
                debug.log('set index: ' + index);
                this.updateUIelements();

                if (useDebounce) {
                    this.setFrameContentDebounce(this.targetFrame, this.dates.getIndex());
                    debug.info('debounce');
                }
                else {
                    this.setFrameContent(this.targetFrame, this.dates.getIndex());
                }
            }
        },

        /**
         * set to previous index
         *
         * @returns {boolean}
         */
        setPrev: function () {
            if (this.dates.getPreviousIndex() !== false) {
                this.set(this.dates.getPreviousIndex(), true);
                return true;
            }
            return false;
        },

        /**
         * set to next index
         *
         * @returns {boolean}
         */
        setNext: function () {
            if (this.dates.getNextIndex() !== false) {
                this.set(this.dates.getNextIndex(), true);
                return true;
            }
            return false;
        },

        /**
         * set frame content
         *
         * @param el
         * @param index
         */
        setFrameContent: function (el, index) {
            var historyDate = (typeof index === 'number') ? this.dates.get(index).historyDate : false;
            var src = this.link;
            if (historyDate) {
                src = (src.includes("?")) ? this.link + "&rex_history_date=" + historyDate : this.link + "?rex_history_date=" + historyDate;
            }
            el.src = src;
            debug.log('update frame content: ' + src);
        },

        /**
         * show history layer
         */
        show: function () {
            debug.log('show layer');
            document.body.style.overflowY = 'hidden'; // fix scroll position
            this.el.style.display = 'block';
        },

        /**
         * hide history layer
         */
        hide: function () {
            debug.log('hide layer');
            this.el.style.display = 'none';
            document.body.style.overflowY = 'auto'; // release scroll position
        },

        /**
         * remove history layer
         */
        remove: function () {
            this.hide();
            debug.log('remove layer');
            this.el.remove();
        },

        /**
         * snap history to new version
         *
         * @param date
         * @returns {*}
         */
        snapVersion: function (date) {
            var url = 'index.php?rex_history_function=snap&history_article_id=' + this.articleId + '&history_language_id=' + this.languageId + '&history_date=' + date + '&_csrf_token=' + encodeURIComponent(history_csrf_token);
            debug.info('snap version: ' + url);
            return request(url).then(function () {
                debug.info('snap success');
            });
        }
    };


    /**
     * Dates
     * handles dates
     *
     * @param config
     * @constructor
     */
    var Dates = function (config) {
        this.all = [];
        if (Array.isArray(config.dates)) {
            this.all = config.dates;
        }
        this.index = this.all.length - 1; // set index to max
        debug.log('new dates:', this.all);
        debug.log('new index: ' + this.index);
    };

    Dates.prototype = {

        /**
         * get date by index
         *
         * @param index
         * @returns {Array|*}
         */
        get: function (index) {
            return (typeof index === 'number') ? this.all[index] : false;
        },

        /**
         * get all dates
         *
         * @returns {Array|*}
         */
        getAll: function () {
            return this.all;
        },

        /**
         * get current date
         *
         * @returns {*}
         */
        getCurrent: function () {
            return this.all[this.index];
        },

        /**
         * get index
         *
         * @returns {number|*}
         */
        getIndex: function () {
            return this.index;
        },

        /**
         * set index
         *
         * @param index
         * @returns {Dates}
         */
        setIndex: function (index) {
            this.index = index;
            return this;
        },

        /**
         * get previous index
         *
         * @returns {*|false}
         */
        getPreviousIndex: function () {
            if (this.index > 0) {
                return this.index - 1;
            }
            return false;
        },

        /**
         * get next index
         *
         * @returns {*|false}
         */
        getNextIndex: function () {
            if (this.index < (this.all.length - 1)) {
                return this.index + 1;
            }
            return false;
        },

        /**
         * get index for timestamp
         *
         * @param timestamp
         * @returns {*|false}
         */
        getIndexForTimestamp: function (timestamp) {
            var index = this.all.findIndex(function (v) {
                return v.timestamp == timestamp;
            });
            return index < 0 ? this.all.length - 1 : index;
        }
    };


    /**
     * Gaps
     * handles gaps between dates
     *
     * @param config
     * @constructor
     */
    var Gaps = function (config) {
        this.dates = config.dates || [];
        this.layoutWidth = config.layoutWidth || 1000;
        this.pipsWidth = config.pipsWidth || 2;
        this.pipsGap = config.pipsGap || 4;

        // init gaps
        this.all = this.getFromDates();
        debug.log('new gaps: ', this.all);

        // equalize gaps
        this.equalize();
        debug.log('equalized gaps: ', this.all);
    };

    Gaps.prototype = {

        /**
         * calculate gap sizes between given date values
         *
         * @param dates
         * @returns {*}
         */
        getFromDates: function () {
            if (Array.isArray(this.dates) && this.dates.length > 0) {
                var gaps = [];
                var datePoints = [];
                for (var i = 0, max = this.dates.length; i < max; i++) {
                    datePoints.push((this.dates[i].timestamp - this.dates[0].timestamp) / (this.dates[this.dates.length - 1].timestamp - this.dates[0].timestamp) * 100);
                    if (i > 0) {
                        gaps.push(datePoints[i] - datePoints[i - 1]);
                    }
                }
                return gaps;
            }
            return false;
        },

        /**
         * set gaps to given value
         *
         * @param value
         * @returns {Gaps}
         */
        set: function (value) {
            this.all = value;
            return this;
        },

        /**
         * get all gaps
         *
         * @returns {Array|*}
         */
        getAll: function () {
            return this.all;
        },

        /**
         * equalize gaps
         * gaps will be expanded to min size with required space taken from wide gaps.
         *
         * @returns {*}
         */
        equalize: function () {
            var items = this.getKeysOfTooNarrowOnes();
            if (items.length > 0) {
                var gaps = this.getAll();
                var minSize = this.getMinSize();
                var sum = 0;
                for (var i = 0, max = items.length; i < max; i++) {
                    sum += (minSize - gaps[items[i]]);
                    gaps[items[i]] = minSize;
                }
                gaps = this.reduceGapsAboveMinSize(gaps, sum);
                this.set(gaps);
                return gaps;
            }
            return false;
        },

        /**
         * calculate minimum gap size (%)
         * 1. in first instance, calculation is based on slider width, pips widths and margins.
         * 2. if number of gaps is very large, their available sizes may be below calculated min size. in this
         *    case we go for the available size as we badly have to place all dates and gaps onto the slider.
         *
         * @returns {number}
         */
        getMinSize: function () {
            var gaps = this.getAll();
            var size = 100 / (this.layoutWidth / (this.pipsWidth + this.pipsGap)); // gap size (%) calculated from predefined slider width, pips widths and margins
            var availableSize = 100 / gaps.length; // gap size (%) calculated from whole number of items placed on slider

            if (size > availableSize) {
                size = availableSize; // limit size
            }
            return size;
        },

        /**
         * find out gaps which are below calculated min gap size
         *
         * @returns {*}
         */
        getKeysOfTooNarrowOnes: function () {
            var gaps = this.getAll();
            var minSize = this.getMinSize();
            if (gaps.length > 0) {
                var invalidGaps = [];
                for (var i = 0, max = gaps.length; i < max; i++) {
                    if (gaps[i] < minSize) {
                        invalidGaps.push(i);
                    }
                }
                return invalidGaps;
            }
            return false;
        },

        /**
         * calculate buffer space from gaps above min gap size
         * we can use this space to realign gaps which are below min gap size to
         * make them more accessible on the slider.
         *
         * @param gaps
         * @param minSize
         * @returns {*}
         */
        calculateBufferSpace: function (gaps, minSize) {
            if (gaps.length > 0) {
                var sum = 0;
                for (var i = 0, max = gaps.length; i < max; i++) {
                    if (gaps[i] > minSize) {
                        sum += gaps[i] - minSize;
                    }
                }
                return sum;
            }
            return false;
        },

        /**
         * reduce gaps above min gap size by given value (proportionately)
         *
         * @param gaps
         * @param value
         * @returns {*}
         */
        reduceGapsAboveMinSize: function (gaps, value) {
            var value = value || 0;
            if (gaps.length > 0 && value > 0) {
                var minSize = this.getMinSize();
                var bufferSpace = this.calculateBufferSpace(gaps, minSize);
                for (var i = 0, max = gaps.length; i < max; i++) {
                    if (gaps[i] > minSize) {
                        gaps[i] -= (value * ((gaps[i] - minSize) / bufferSpace));
                    }
                }
            }
            return gaps;
        }
    };


    /**
     * Slider
     * handles slider data
     *
     * @param config
     * @constructor
     */
    var Slider = function (config) {
        this.dates = config.dates || [];
        this.gaps = config.gaps || [];
        this.currentDate = this.dates[this.dates.length - 1];
        this.currentDateText = config.currentDateText;
        this.containerElement = config.containerElement;

        // init slider
        this.noUiSlider = this.initSlider();
        debug.log('new slider: ', this.noUiSlider);

        // inject HTML for range markers into slider element
        this.injectMarkersHtml();
    };

    Slider.prototype = {

        /**
         * get history date for timestamp
         *
         * @param timestamp
         * @returns {*|false}
         */
        getHistoryDate: function (timestamp) {
            var date = this.dates.find(function (v) {
                return v.timestamp == timestamp;
            });
            return date.historyDate;
        },

        /**
         * get slider range
         *
         * @returns {*}
         */
        getRange: function () {
            if (this.dates.length > 0 && this.gaps.length > 0) {
                var range = {};
                var progress = 0;
                for (var i = 0, max = this.dates.length; i < max; i++) {
                    switch (true) {
                        case (i === 0):
                            range['min'] = this.dates[i].timestamp;
                            break;
                        case (i === max - 1):
                            range['max'] = this.dates[i].timestamp;
                            break;
                        default:
                            range[progress + '%'] = this.dates[i].timestamp;
                            break;
                    }
                    if (this.gaps[i]) {
                        progress += this.gaps[i];
                    }
                }
                return range;
            }
            return false;
        },

        /**
         * get markers HTML
         *
         * @returns {*}
         */
        getMarkersHtml: function () {
            if (this.dates.length > 0 && this.gaps.length > 0) {
                var html = '';
                var progress = 0;
                var epochClass = '';
                for (var i = 0, max = this.dates.length; i < max; i++) {

                    if (this.dates[i].timestamp < this.currentDate.timestamp) {
                        epochClass = 'history-slider-rangemarker--past';
                    }
                    else {
                        epochClass = 'history-slider-rangemarker--future';
                    }
                    html += '<div class="history-slider-rangemarker ' + epochClass + '" style="left: ' + progress + '%;"></div>';

                    if (this.gaps[i]) {
                        progress += this.gaps[i];
                    }
                }
                return html;
            }
            return false;
        },

        /**
         * inject markers HTML
         */
        injectMarkersHtml: function () {
            this.containerElement.querySelector('.noUi-base').insertAdjacentHTML('beforeend', this.getMarkersHtml());
        },

        /**
         * init slider
         *
         * @returns {*}
         */
        initSlider: function () {
            if (this.containerElement) {
                var that = this;
                return noUiSlider.create(that.containerElement, {
                    animationDuration: 150,
                    behaviour: 'tap',
                    tooltips: {
                        to: function (value) {
                            if (value === that.currentDate.timestamp) {
                                return '<span>' + that.currentDateText.replace(/\s/, '</span><span>') + '</span>';
                            }
                            return '<span>' + that.getHistoryDate(value).replace(/\s/, '</span><span>') + '</span>';
                        },
                        from: function (value) {
                            return value;
                        }
                    },
                    snap: true,
                    range: that.getRange(),
                    start: that.currentDate.timestamp,
                    pips: {
                        mode: 'steps',
                        density: -1,
                        stepped: true
                    },
                    format: {
                        to: function (value) {
                            return parseInt(value, 10);
                        },
                        from: function (value) {
                            return value;
                        }
                    }
                });
            }
            return false;
        }
    };

}());
