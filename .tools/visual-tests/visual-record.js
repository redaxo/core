/**
 * REDAXO Visual Regression testing
 *
 * 1. Start a local php-server with `php -S localhost:80` from within the project root
 * 2. Make sure a database server is running
 * 3. Make sure a admin-user with login `myusername` and password `mypassword` exists
 * 4. Make sure the REDAXO instance running at START_URL is accessible and login screen appears on the url
 * 5. Start the visual recording with `node .tools/visual-tests/visual-record.js`
 */

import playwright from 'playwright';
import pixelmatch from 'pixelmatch';
import {PNG} from 'pngjs';
import fs from 'fs';
import {mkdirp} from 'mkdirp';

const viewportWidth = 1280;
const viewportHeight = 800;

const START_URL = 'http://localhost:8080/redaxo/index.php';
const DEBUGGING = false;
const WORKING_DIR = '.tests-visual/';
const GOLDEN_SAMPLES_DIR = '.tools/visual-tests/screenshots/';

const myArgs = process.argv.slice(2);
let minDiffPixels = 1;
let isSetup = false;
//  overall exit-code
let exitCode = 0;
const expectedFiles = new Set();

if (myArgs.includes('regenerate-all')) {
    // force sample-regeneration, even if pixelmatch() thinks nothing changed
    minDiffPixels = 0;
}
if (myArgs.includes('setup')) {
    isSetup = true;
}
const MIN_DIFF_PIXELS = minDiffPixels;

// htaccess ajax checks are subject to race conditions and therefore generated 'random' markup.
// disable the check to get less visual noise.
const noHtaccessCheckCookie = {
    name: 'rex_htaccess_check',
    value: '1',
    domain: 'localhost',
    path: '/',
    httpOnly: false,
    secure: false
};

// all pages
const allPages = {
    'structure_category_edit.png': START_URL + '?page=structure&category_id=0&article_id=0&clang=1&edit_id=1&function=edit_cat&catstart=0',
    'structure_article_edit.png': START_URL + '?page=content/edit&category_id=1&article_id=1&clang=1&mode=edit',
    'structure_article_functions.png': START_URL + '?page=content/functions&article_id=1&category_id=1&clang=1&ctype=1',
    'structure_slice_edit.png': START_URL + '?page=content/edit&article_id=1&slice_id=3&clang=1&ctype=1&function=edit#slice3',

    'mediapool_media.png': START_URL + '?page=mediapool/media',
    'mediapool_media_file.png': START_URL + '?page=mediapool/media&file_id=1&rex_file_category=0',
    'mediapool_upload.png': START_URL + '?page=mediapool/upload',
    'mediapool_structure.png': START_URL + '?page=mediapool/structure',
    'mediapool_sync.png': START_URL + '?page=mediapool/sync',

    'templates.png': START_URL + '?page=templates',
    'templates_add.png': START_URL + '?page=templates&function=add',
    'templates_edit.png': START_URL + '?page=templates&function=edit&template_id=1',

    'modules_modules.png': START_URL + '?page=modules/modules',
    'modules_modules_add.png': START_URL + '?page=modules/modules&function=add',
    'modules_actions.png': START_URL + '?page=modules/actions',
    'modules_actions_add.png': START_URL + '?page=modules/actions&function=add',

    'users_users.png': START_URL + '?page=users/users',
    'users_roles.png': START_URL + '?page=users/roles',
    'users_role_add.png': START_URL + '?page=users/roles&func=add&default_value=1',

    'packages.png': START_URL + '?page=packages',
    'packages_help.png': START_URL + '?page=packages&subpage=help&package=project',

    'system_settings.png': START_URL + '?page=system/settings',
    'system_lang.png': START_URL + '?page=system/lang',
    'system_log.png': START_URL + '?page=system/log/redaxo',
    'system_report.png': START_URL + '?page=system/report/html',

    'backup_export.png': START_URL + '?page=backup/export',
    'backup_import.png': START_URL + '?page=backup/import',
    'backup_import_server.png': START_URL + '?page=backup/import/server',

    'cronjob_cronjobs.png': START_URL + '?page=cronjob/cronjobs',
    'cronjob_cronjobs_add.png': START_URL + '?page=cronjob/cronjobs&func=add',

    'media_manager_types.png': START_URL + '?page=media_manager/types',
    'media_manager_types_add.png': START_URL + '?page=media_manager/types&func=add',
    'media_manager_types_edit.png': START_URL + '?page=media_manager/types&type_id=4&effects=1',
    'media_manager_settings.png': START_URL + '?page=media_manager/settings',

    'metainfo_articles_add.png': START_URL + '?page=metainfo/articles&func=add',
    'metainfo_categories.png': START_URL + '?page=metainfo/categories',
    'metainfo_clangs.png': START_URL + '?page=metainfo/clangs',

    'phpmailer_config.png': START_URL + '?page=phpmailer/config',
};

function countDiffPixels(img1path, img2path ) {
    if (!fs.existsSync(img2path)) {
        // no reference image
        // we assume a new reference screenshot will be added
        return MIN_DIFF_PIXELS;
    }

    const img1 = PNG.sync.read(fs.readFileSync(img1path));
    const img2 = PNG.sync.read(fs.readFileSync(img2path));

    if (img1.width !== img2.width || img1.height !== img2.height) {
        // different image sizes
        // we assume a new reference screenshot will be added
        return MIN_DIFF_PIXELS;
    }

    return pixelmatch(img1.data, img2.data, null, img1.width, img1.height, {threshold: 0.1});
}

async function processScreenshot(page, screenshotName) {
    mkdirp.sync(WORKING_DIR);

    await page.waitForSelector('body');
    await page.evaluate(() => {
        // disable spellchecker to prevent red wavy underlines in inputs
        document.documentElement.setAttribute('spellcheck', 'false');
        // hide blinking cursor/icon
        // disable animations / transitions
        // disable box-shadow on navbar to prevent visual noise by https://github.com/redaxo/core/blob/97a08682b965cfd3f94e2a5ffa219501544cf71c/redaxo/src/addons/be_style/plugins/redaxo/assets/javascripts/redaxo.js#L211-L222

        document.body.insertAdjacentHTML('beforeend', `<style type="text/css">input { caret-color: transparent !important; } * { animation: initial !important; transition: none !important; mix-blend-mode: unset !important;} .navbar {box-shadow: none !important; } body {font-family: sans-serif;} #rex-js-main-sidebar{opacity: 1 !important;} #rex-js-nav-top{position: absolute; transform: none !important;}</style>`);
    });

    // mask dynamic content, to make it not appear like change (visual noise)
    await page.evaluate(function() {
        var changingElements = [
            '.rex-js-script-time',
            '#rex-page-setup .panel-success li:first-child b',
            '.rex-js-setup-step-4 .form-control-static',
            'td[data-title="Letzter Login"]',
            '#rex-form-exportfilename',
            '#rex-page-system-settings .col-lg-4 td',
            '#rex-page-system-report-html .row td',
            'td[data-title="Version"]',
            'td[data-title="Erstellt am"]',
            'tr[class^="rex-state-"] td[data-title="Zeit"]', // system log items
            'td[data-title="Sitzungs-ID"]',
            'td[data-title="User-Agent"]',
            'td[data-title="Startzeit"]',
            'td[data-title="Letzte Aktivität"]'
        ];

        changingElements.forEach(function (selector) {
            var els = document.querySelectorAll(selector);

            els.forEach(function (el) {
                if (el) {
                    el.innerHTML = 'XXX';
                    el.value = 'XXX'; // handle input elements
                }
            });
        });
    });

    await page.screenshot({ path: WORKING_DIR + screenshotName, fullPage: true, animations: 'disabled' });

    // make sure we only create changes in .tools/visual-tests/screenshots/ on substential screenshot changes.
    // this makes sure to prevent endless loops within the github action
    let diffPixels = countDiffPixels(WORKING_DIR + screenshotName, GOLDEN_SAMPLES_DIR + screenshotName);
    console.log("DIFF-PIXELS: "+ screenshotName + ":" +diffPixels);
    if (diffPixels >= MIN_DIFF_PIXELS) {
        fs.renameSync(WORKING_DIR + screenshotName, GOLDEN_SAMPLES_DIR + screenshotName);
    }
}

async function createScreenshots(page, screenshotName) {
    const darkName = screenshotName.replace('.png', '--dark.png');
    expectedFiles.add(screenshotName);
    expectedFiles.add(darkName);

    await page.emulateMedia({ colorScheme: 'light' });
    await processScreenshot(page, screenshotName);

    await page.emulateMedia({ colorScheme: 'dark' });
    await processScreenshot(page, darkName);
}

async function logIntoBackend(page, username = 'myusername', password = '91dfd9ddb4198affc5c194cd8ce6d338fde470e2') {
    await goToUrlOrThrow(page, START_URL, { waitUntil: 'load' });
    await page.type('#rex-id-login-user', username);
    await page.type('#rex-id-login-password', password); // sha1('mypassword')
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.$eval('#rex-form-login', form => form.submit()),
    ]);
}

async function goToUrlOrThrow(page, url, options, maxretries = 5) {
    if(0 === maxretries) {
        console.error('Failing too hard.... abort');
        process.exit(1);
        return;
    }

    // prevent timeouts on slower pages
    options.timeout = 0;

    let response;
    try {
      response = await page.goto(url, options);
      if (!response.ok() && response.status() != 304) {
        const error = `Failed to load ${url}: the server responded with a status of ${response.status()} (${response.statusText()})`;
        console.error("::error ::" +error);
        exitCode = 1;
      }
      await response;
    } catch (e) {
        console.error(e);
        if (response) {
            console.error(response.status);
            console.error(response.statusText());
        }
        console.log('Request failed, retry ' + maxretries + ' more times');
        await goToUrlOrThrow(page, url, options, --maxretries);
    }
}

const CONCURRENCY = 4;

function setupPageConsoleLogging(page) {
    page.on('console', function(msg) {
        const text = msg.text();
        if (text.indexOf("Unrecognized feature: 'interest-cohort'.") !== -1) {
            return;
        }

        if (text.indexOf("Error with Permissions-Policy header: Origin trial controlled feature not enabled: 'interest-cohort'") !== -1) {
            return;
        }

        // ajax requests
        const origin = msg.location().url;
        if (origin) {
            console.log(`BROWSER-CONSOLE: "${origin}" (Ajax)`, text);
            return;
        }

        console.log('BROWSER-CONSOLE:', text);
    });
}

async function createWorker(browser) {
    const context = await browser.newContext();
    await context.addCookies([noHtaccessCheckCookie]);
    const page = await context.newPage();
    setupPageConsoleLogging(page);
    await page.setViewportSize({ width: viewportWidth, height: viewportHeight });
    await logIntoBackend(page);
    return { context, page };
}

async function processAllPagesParallel(browser) {
    const entries = Object.entries(allPages);

    // create workers sequentially to avoid race conditions during login (cache dir creation)
    const workers = [];
    for (let i = 0; i < CONCURRENCY; i++) {
        workers.push(await createWorker(browser));
    }

    let index = 0;

    async function runWorker(page) {
        while (index < entries.length) {
            const currentIndex = index++;
            const [fileName, url] = entries[currentIndex];
            await goToUrlOrThrow(page, url, { waitUntil: 'load' });
            await createScreenshots(page, fileName);
        }
    }

    await Promise.all(workers.map(({ page }) => runWorker(page)));

    // close worker contexts
    await Promise.all(workers.map(({ context }) => context.close()));
}

async function main() {
    const browser = await playwright.chromium.launch(
        DEBUGGING ? { headless: false } : {}
    );
    const context = await browser.newContext();
    await context.addCookies([noHtaccessCheckCookie]);

    switch (true) {

        case isSetup: {
            let page = await context.newPage();
            setupPageConsoleLogging(page);
            await page.setViewportSize({ width: viewportWidth, height: viewportHeight });

            // setup step 1
            await goToUrlOrThrow(page, START_URL, { waitUntil: 'load' });
            await createScreenshots(page, 'setup.png');

            // setup steps 2-5
            for (var step = 2; step <= 5; step++) {
                // step 2: wait until `networkidle0` to finish AJAX requests, see https://github.com/puppeteer/puppeteer/blob/main/docs/api.md#pagegotourl-options
                await goToUrlOrThrow(page, START_URL + '?page=setup&lang=de_de&step=' + step, { waitUntil: step === 2 ? 'networkidle0' : 'load'});
                await createScreenshots(page, 'setup_' + step + '.png');
            }

            // step 6
            // requires form in step 5 to be submitted
            await page.$eval('.rex-js-createadminform', form => form.submit());
            await page.waitForTimeout(200);
            await createScreenshots(page, 'setup_6.png');

            await page.close();
            break;
        }

        default: {
            let page = await context.newPage();
            setupPageConsoleLogging(page);
            await page.setViewportSize({ width: viewportWidth, height: viewportHeight });

            // login page
            await goToUrlOrThrow(page, START_URL, { waitUntil: 'load' });
            await page.waitForSelector('.rex-background--ready');
            await page.waitForTimeout(200); // wait for bg image to fade in
            await createScreenshots(page, 'login.png');

            // login successful
            await logIntoBackend(page);
            await createScreenshots(page, 'index.png');

            // run through all pages in parallel
            await processAllPagesParallel(browser);

            // the following steps have side effects and must run sequentially on a single page

            await goToUrlOrThrow(page, START_URL + '?page=users/users&user_id=1', { waitUntil: 'load' });
            await createScreenshots(page, 'users_edit.png');

            // test safe mode
            await goToUrlOrThrow(page, START_URL + '?page=system/settings', { waitUntil: 'load' });
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load' }),
                page.click('.btn-safemode-activate') // enable safe mode
            ]);
            await createScreenshots(page, 'system_settings_safemode.png');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'load' }),
                page.click('.btn-safemode-deactivate') // disable safe mode again
            ]);

            // test debug
            const debugApiPattern = /rex-api-call=debug/;
            const abortDebugApi = route => route.abort();
            await page.route(debugApiPattern, abortDebugApi);
            await goToUrlOrThrow(page, START_URL + '?page=debug', { waitUntil: 'load' });
            await createScreenshots(page, 'debug_clockwork.png');
            await page.unroute(debugApiPattern, abortDebugApi);

            // test customizer
            await goToUrlOrThrow(page, START_URL + '?page=packages', { waitUntil: 'load' });
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle0' }),
                page.click('#package-be_style-customizer .rex-table-action > a:first-child') // install
            ]);
            await createScreenshots(page, 'packages_customizer_installed.png');
            await goToUrlOrThrow(page, START_URL + '?page=system/customizer', { waitUntil: 'load' });
            await createScreenshots(page, 'system_customizer.png');

            // metainfo
            for (const type of ['articles', 'media']) {
                await goToUrlOrThrow(page, START_URL + '?page=metainfo/' + type, { waitUntil: 'load' });
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'load' }),
                    page.click('.btn[href*="rex-api-call=metainfo_default_fields_create"]') // install default fields
                ]);
                await page.waitForSelector('.alert-success');
                await createScreenshots(page, 'metainfo_' + type + '.png');
            }

            // logout
            await page.click('#rex-js-nav-top .rex-logout');
            await page.waitForSelector('.rex-background--ready');
            await page.waitForTimeout(200); // wait for bg image to fade in
            await createScreenshots(page, 'logout.png');

            await page.close();
            break;
        }
    }

    await context.close();
    await browser.close();

    // delete stale screenshots that are no longer generated by this run
    if (fs.existsSync(GOLDEN_SAMPLES_DIR)) {
        for (const file of fs.readdirSync(GOLDEN_SAMPLES_DIR)) {
            if (!file.endsWith('.png') || expectedFiles.has(file)) {
                continue;
            }
            // setup and default run as separate processes,
            // so only clean up files matching the current run's scope
            if (isSetup !== file.startsWith('setup')) {
                continue;
            }
            console.log('DELETING STALE SCREENSHOT: ' + file);
            fs.unlinkSync(GOLDEN_SAMPLES_DIR + file);
        }
    }

    process.exit(exitCode);
}

// print uncaught exceptions and make github action fail
main().catch(error => {
    console.error("::error ::" +
        error.toString()
             .replace(/%/g, '%25')
             .replace(/\r/g, '%0D')
             .replace(/\n/g, '%0A')
    );
    process.exit(1);
});
