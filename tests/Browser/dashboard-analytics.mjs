// Run against HTML captured from DashboardAnalyticsTest's isolated database.
// Accepts a Playwright Browser instance; no production accounts or writes are used.
export async function verifyDashboardAnalytics(browser, baseUrl) {
    const roles = ['celp_secretary', 'coordinator', 'celp_head', 'csa_director', 'student_org', 'college_department', 'participant'];
    const expectedTotals = {celp_secretary: 21, coordinator: 21, celp_head: 27, csa_director: 21, student_org: 9, college_department: 4, participant: 4};
    const results = [];
    for (const role of roles) {
        const result = {role, checks: [], errors: [], layouts: []};
        const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'no-preference'});
        const page = await context.newPage();
        const check = (name, passed, detail = undefined) => result.checks.push({name, passed: Boolean(passed), ...(detail === undefined ? {} : {detail})});
        let navigations = 0, popups = 0;
        page.on('pageerror', error => result.errors.push(String(error)));
        page.on('console', message => { if (message.type() === 'error') result.errors.push(message.text()); });
        page.on('popup', () => popups++);
        page.on('framenavigated', frame => { if (frame === page.mainFrame()) navigations++; });
        const goto = async (empty = false) => {
            await page.goto(`${baseUrl}/fixtures/after/${empty ? 'empty-' : ''}${role}`);
            await page.locator('[data-analytics-state="ready"]').waitFor();
        };
        try {
            await goto();
            const chart = page.locator('[data-analytics-columns]');
            const config = await page.locator('[data-analytics-config]').evaluate(element => JSON.parse(element.textContent));
            const totals = config.months.map(month => config.series.reduce((sum, series) => sum + month[series.key], 0));
            const sum = values => values.reduce((total, value) => total + value, 0);
            const displayedTotal = async () => Number((await chart.locator('[data-period-total]').textContent()).replaceAll(',', ''));
            check('Exact role-specific six-month total', await displayedTotal() === expectedTotals[role] && sum(totals) === expectedTotals[role]);
            check('Every supplied value is an unsigned whole count', config.months.every(month => config.series.every(series => Number.isInteger(month[series.key]) && month[series.key] >= 0)));
            check('Six month columns, integer axis, no loading skeleton after render', await chart.locator('.pl-column-hit').count() === 6 && await chart.locator('[data-chart-skeleton]').count() === 0 && await chart.locator('.pl-axis-label').evaluateAll(elements => elements.filter(element => /^\d/.test(element.textContent)).every(element => /^\d[\d,]*$/.test(element.textContent))));
            const navigationBeforeFilters = navigations;
            if (config.series.length > 1) {
                await chart.locator('[data-chart-mode="total"]').click();
                check('Total switch retains exact values and rounded positive columns', await chart.locator('[data-column-bars] [data-count]').evaluateAll(elements => elements.reduce((sum, element) => sum + Number(element.dataset.count), 0)) === sum(totals));
                check('Columns animate on a data transition', await chart.evaluate(element => element.getAnimations({subtree: true}).some(animation => animation.playState === 'running')));
                await chart.locator(`[data-chart-mode="${config.mode}"]`).click();
            }
            await chart.locator('.pl-column-hit').last().hover();
            check('Hover tooltip shows the correct month and count', (await page.locator('.pl-analytics-tooltip').textContent()).includes(config.months[5].periodLabel) && (await page.locator('.pl-analytics-tooltip').textContent()).includes(String(totals[5])));
            check('Tooltip stays in the viewport', await page.locator('.pl-analytics-tooltip').evaluate(element => {const r = element.getBoundingClientRect();return r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight;}));
            await page.keyboard.press('Escape');
            await chart.locator('.pl-column-hit').first().focus();
            check('Keyboard focus exposes the same accurate tooltip', (await page.locator('.pl-analytics-tooltip').textContent()).includes(config.months[0].periodLabel));
            await page.keyboard.press('Escape');
            await chart.locator('[data-analytics-period] summary').click();
            await chart.locator('[data-period-preset="3"]').click();
            check('Last-three-month filter updates columns, summary and data table', await chart.locator('.pl-column-hit').count() === 3 && await displayedTotal() === sum(totals.slice(3)) && await chart.locator('[data-chart-table] tbody tr').count() === 3);
            await chart.locator('[data-analytics-period] summary').click();
            await chart.locator('[data-period-from]').selectOption('5');
            await chart.locator('[data-period-to]').selectOption('3');
            check('Custom date ranges stay valid and update exact counts', await chart.locator('[data-period-from]').inputValue() === '3' && await chart.locator('[data-period-to]').inputValue() === '3' && await displayedTotal() === totals[3]);
            await chart.locator('[data-period-preset="all"]').click();
            check('Filters never reload or navigate the page', navigations === navigationBeforeFilters && await displayedTotal() === sum(totals));
            const radial = page.locator('[data-analytics-radial]');
            if (role !== 'participant') {
                await radial.scrollIntoViewIfNeeded();
                await page.waitForFunction(() => [...document.querySelectorAll('[data-analytics-radial] [data-count-up]')].every(element => Number(element.textContent.replaceAll(',', '')) === Number(element.dataset.countUp)));
                check('Radial center, legend and percentages agree', await radial.evaluate(element => {const items=[...element.querySelectorAll('[data-radial-item]')];const total=items.reduce((sum,item)=>sum+Number(item.dataset.tooltipValue),0);return Number(element.querySelector('.pl-ring-total').textContent.replaceAll(',',''))===total && items.every(item=>Number(item.dataset.tooltipPercent) === (total ? Math.round(Number(item.dataset.tooltipValue)/total*1000)/10 : 0));}));
                await radial.locator('[data-radial-legend]').last().hover();
                check('Radial legend highlights a ring and exposes its count and share', await radial.locator('.pl-radial-row.is-active').count() === 1 && (await page.locator('.pl-analytics-tooltip').textContent()).includes('Share'));
            } else {
                check('Participant keeps attendance chart without an invented pipeline', await radial.count() === 0 && await page.locator('[data-analytics-pipeline]').count() === 0);
            }
            if (['student_org', 'college_department'].includes(role)) {
                check('Pipeline retains the four actual review offices', await page.locator('[data-analytics-pipeline] .pl-pipeline-step').count() === 4 && await page.locator('[data-analytics-pipeline]').evaluate(element => [...element.querySelectorAll('a')].every(link => link.origin === location.origin && link.search.includes('status='))));
            }
            for (const width of [1440, 1280, 1024, 768, 390, 320]) {
                await page.setViewportSize({width, height: 900});
                await chart.scrollIntoViewIfNeeded();
                await page.waitForFunction(() => Math.abs(document.querySelector('.pl-column-svg').viewBox.baseVal.width - Math.max(240, Math.round(document.querySelector('[data-chart-canvas]').clientWidth))) <= 1);
                const layout = await page.evaluate(() => ({width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth + 1, clipped: [...document.querySelectorAll('.pl-analytics svg')].some(element => {const r=element.getBoundingClientRect();return r.left < -1 || r.right > innerWidth + 1;}), rowHeights: [...document.querySelectorAll('.org-chart-layout,.csd-chart-grid,.cd-chart-grid,.csa-chart-layout,.chd-chart-grid')].map(element => [...element.children].filter(child => child.getBoundingClientRect().height > 0).map(child => Math.round(child.getBoundingClientRect().height)))}));
                result.layouts.push(layout);
                check(`Responsive ${width}px without chart clipping or page overflow`, layout.width === width && !layout.overflow && !layout.clipped, layout);
                if (width === 1440 && layout.rowHeights.length) check('Adjacent chart cards have matching heights', layout.rowHeights.every(heights => Math.max(...heights) - Math.min(...heights) <= 1));
            }
            const zoomSession = await context.newCDPSession(page);
            for (const scale of [1.25, 2]) {
                const width = Math.round(1440 / scale), height = Math.round(1000 / scale);
                await zoomSession.send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: scale, mobile: false});
                await chart.scrollIntoViewIfNeeded();
                const zoom = await page.evaluate(() => ({width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth + 1, clipped: [...document.querySelectorAll('.pl-analytics svg')].some(element => element.getBoundingClientRect().right > innerWidth + 1)}));
                check(`${scale * 100}% zoom-equivalent viewport remains readable`, zoom.width === width && !zoom.overflow && !zoom.clipped, zoom);
            }
            await zoomSession.send('Emulation.clearDeviceMetricsOverride');
            await zoomSession.detach();
            await page.setViewportSize({width: 1440, height: 1000});
            await goto();
            const linked = role === 'celp_head' || ['student_org', 'college_department'].includes(role) ? page.locator('.pl-column-hit[href]').first() : page.locator('[data-radial-legend="0"][href]');
            if (await linked.count()) {
                const destination = await linked.getAttribute('href');
                const before = navigations;
                await linked.click();
                await page.waitForURL(destination);
                check('One click performs exactly one same-tab record navigation', navigations === before + 1 && popups === 0 && context.pages().length === 1);
                if (['student_org', 'college_department'].includes(role)) {
                    const visibleRows = await page.locator('#proposal-table tbody tr:visible').count();
                    check('Monthly proposal drill-down shows exactly the matching owned records', visibleRows === totals[0] && !(await page.locator('body').textContent()).includes('Other Organization proposal'));
                }
                await page.goBack();
                await page.locator('[data-analytics-state="ready"]').waitFor();
                await page.goForward();
                check('Browser Back and Forward preserve normal navigation', page.url() === destination && popups === 0);
            }
            await goto(true);
            check('Empty charts render actual zero and no misleading positive bars or arcs', await displayedTotal() === 0 && await page.locator('[data-chart-empty]').isVisible() && await page.locator('.pl-bar,.pl-ring-value').count() === 0 && await page.locator('.pl-ring-total').evaluateAll(elements => elements.every(element => element.textContent.trim() === '0')));
            await page.emulateMedia({reducedMotion: 'reduce'});
            await goto();
            check('Reduced motion renders final values without chart animations', await page.locator('.pl-analytics,[data-analytics-pipeline]').evaluateAll(elements => elements.every(element => element.getAnimations({subtree:true}).every(animation => animation.playState !== 'running'))) && await page.locator('[data-count-up]').evaluateAll(elements => elements.every(element => Number(element.textContent.replaceAll(',','')) === Number(element.dataset.countUp))));
            let releaseModule;
            const heldModule = new Promise(resolve => releaseModule = resolve);
            const hold = async route => { await heldModule; await route.continue(); };
            await context.route('**/dashboard-analytics-*.js', hold);
            const loadingPage = await context.newPage();
            try {
                await loadingPage.goto(`${baseUrl}/fixtures/after/${role}`, {waitUntil: 'commit'});
                await loadingPage.locator('[data-chart-skeleton]').waitFor({state: 'visible'});
                check('Slow module load exposes a skeleton, accurate data table and disabled filters', await loadingPage.locator('[data-analytics-columns]').getAttribute('aria-busy') === 'true' && await loadingPage.locator('[data-period-from]').isDisabled() && await loadingPage.locator('[data-chart-table] tbody tr').count() === 6);
            } finally {
                releaseModule();
                await loadingPage.locator('[data-analytics-state="ready"]').waitFor();
                await loadingPage.close();
                await context.unroute('**/dashboard-analytics-*.js', hold);
            }
            result.performance = await page.evaluate(() => ({domNodes: document.querySelectorAll('*').length, domContentLoadedMs: Math.round(performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd), chartResources: performance.getEntriesByType('resource').filter(entry => /dashboard-analytics/.test(entry.name)).map(entry => ({name: entry.name.split('/').pop(), bytes: entry.decodedBodySize}))}));
            check('No browser console or JavaScript errors', result.errors.length === 0, result.errors);
        } catch (error) {
            check('Browser check completed', false, String(error));
        } finally {
            await context.close();
        }
        results.push(result);
    }
    return results;
}
