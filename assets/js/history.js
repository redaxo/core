(function ($) {

    var DEBUGMODE = false;

    $(document).on("rex:ready", function (event, container) {

        // set debug mode
        if ($('.rex-is-debugmode').length) {
            DEBUGMODE = true;
        }

        // trigger elements for opening and closing history layer
        container.on("click", '[data-history-layer]', function (e) {
            e.preventDefault();
            switch ($(this).data('history-layer')) {
                case 'open':
                    var historyLayer = new HistoryLayer('#content-history-layer');
                    break;
                case 'close':
                    historyLayer.close();
                    break;
            }
        });
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
     * History layer
     *
     * @constructor
     */
    var HistoryLayer = function (el) {

        // use variables from HTML
        this.articleId = history_article_id;
        this.clangId = history_clang_id;
        this.ctypeId = history_ctype_id;
        this.link = history_article_link;

        // set up environment ojects
        this.dates = {};
        this.gaps = {};
        this.slider = {};

        // set debouncer for frame content change
        this.setFrameContentDebounce = debounce(this.setFrameContent.bind(this), 500);

        // init layer content
        var that = this;
        $.when(this.load(this.articleId, this.clangId)).then(function (data) {

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
                    currentDateText: that.selector.find('option')[0].innerText,
                    containerElement: $('.history-layer-slider', that.el)
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
         * @param clangId
         * @returns {*}
         */
        load: function (articleId, clangId) {
            var url = 'index.php?rex_history_function=layer&history_article_id=' + articleId + '&history_clang_id=' + clangId;
            debug.info('load: ' + url);
            return $.ajax({
                url: url,
                context: document.body
            }).done(function () {
                debug.info('load success');
            }).fail(function (jqXHR, textStatus) {
                debug.error('load failure: ' + textStatus);
            });
        },

        /**
         * calculate content width
         * used for slider calculation later on
         * requires layer to render first
         */
        calculateContentWidth: function () {
            this.el.fadeOut(0).show();
            var width = $('.history-layer-layout', this.el).width();
            debug.log('calculate layer content width: ' + width);
            this.el.hide().fadeIn(0);
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
            $.each(this.selector.find('option'), function (index, option) {
                if ($(option).val()) {
                    dates.push({
                        timestamp: parseInt($(option).val(), 10),
                        historyDate: $(option).data('history-date')
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
            if ($(el).length) {
                $(el).replaceWith(data);
            }
            else {
                $("body").append(data);
            }
        },

        /**
         * init UI elements
         * @param el
         */
        initUiElements: function (el) {
            debug.log('init UI elements');
            this.el = $(el);
            this.selector_current = $('#content-history-select-date-1', this.el);
            this.selector = $('#content-history-select-date-2', this.el);
            this.selectPrev = $('[data-history-layer="prev"]', this.el);
            this.selectNext = $('[data-history-layer="next"]', this.el);
            this.currentFrame = $('#content-history-iframe-1', this.el);
            this.targetFrame = $('#content-history-iframe-2', this.el);
            this.submit = $('[data-history-layer="snap"]', this.el);
            this.cancel = $('[data-history-layer="cancel"]', this.el);

            // fix layout for browsers having flexbox issues (Safari < 11 most notably)
            // they require a px based flex container to properly calculate childs’ height
            // unfortunately, this results in removing the responsiveness ¯\_(ツ)_/¯
            // see: https://github.com/philipwalton/flexbugs#14-flex-containers-with-wrapping-the-container-is-not-sized-to-contain-its-items
            // use timeout to run late!
            window.setTimeout(function () {
                this.wrapper = $('.history-layer-panel-3', this.el);
                this.target = $('.history-responsive-container', this.el);

                if (this.target.height() < 1) {
                    this.wrapper.css('height', this.wrapper.height());
                }
            }.bind(this), 1);
        },

        /**
         * bind event handlers
         */
        bindEventHandlers: function () {

            var that = this;

            debug.log('bind event handlers');
            this.selector.on('change', $.proxy(this.onSelect, this));
            this.selectPrev.on('click', $.proxy(this.onSelectPrev, this));
            this.selectNext.on('click', $.proxy(this.onSelectNext, this));
            this.submit.on('click', $.proxy(this.onSubmit, this));
            this.cancel.on('click', $.proxy(this.onCancel, this));

            if (this.sliderSelect) {
                this.sliderSelect.on('change', $.proxy(this.onSliderSelect, this));
            }
            this.selector_current.on('change', function () {
                var revision = that.selector_current.val();
                var src = that.link + "?rex_version=" + revision;
                that.currentFrame.attr("src", src);
            })
        },

        /**
         * on selector change
         */
        onSelect: function () {
            debug.log('select ' + this.selector.val());
            this.set(this.dates.getIndexForTimestamp(this.selector.val()));
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
            $.when(this.snapVersion(historyDate).then(function () {
                that.remove();

                // reload redaxo page
                var url = 'index.php?page=content/edit&article_id=' + that.articleId + '&clang_id=' + that.clangId + '&ctype=' + that.ctypeId;
                $.pjax({url: url, container: '#rex-js-page-main-content', fragment: '#rex-js-page-main-content'})
            }));
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
                this.selector.val(''); // option value for newest history date is empty!
            }
            else {
                this.selector.val(this.dates.getCurrent().timestamp);
            }
            this.selectPrev.prop('disabled', this.dates.getPreviousIndex() === false);
            this.selectNext.prop('disabled', this.dates.getNextIndex() === false);

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
            el.attr("src", src);
            debug.log('update frame content: ' + src);
        },

        /**
         * show history layer
         */
        show: function () {
            debug.log('show layer');
            $('body').css('overflow-y', 'hidden'); // fix scroll position
            this.el.show();
        },

        /**
         * hide history layer
         */
        hide: function () {
            debug.log('hide layer');
            this.el.hide();
            $('body').css('overflow-y', 'auto'); // release scroll position
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
            var url = 'index.php?rex_history_function=snap&history_article_id=' + this.articleId + '&history_clang_id=' + this.clangId + '&history_date=' + date;
            debug.info('snap version: ' + url);
            return $.ajax({
                url: url,
                context: document.body
            }).done(function () {
                debug.info('snap success');
            }).fail(function (jqXHR, textStatus) {
                debug.error('snap failure: ' + textStatus);
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
            var index = this.all.length - 1; // max
            $.each(this.all, function (i, v) {
                if (v.timestamp == timestamp) {
                    index = i;
                    return false;
                }
            });
            return index;
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
            var index = false;
            $.each(this.dates, function (i, v) {
                if (v.timestamp == timestamp) {
                    index = i;
                    return false;
                }
            });
            return this.dates[index].historyDate;
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
            $('.noUi-base', this.containerElement).append(this.getMarkersHtml());
        },

        /**
         * init slider
         *
         * @returns {*}
         */
        initSlider: function () {
            if (this.containerElement[0]) {
                var that = this;
                return noUiSlider.create(that.containerElement[0], {
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

}(jQuery));
