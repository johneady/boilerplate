/**
 * Wires the two public agent calculators to their pages. Plain JavaScript,
 * no framework: the arithmetic is in ./calculators/formulas.js and every
 * variable it uses comes from config/calculators.php, which the page embeds
 * as JSON in <script id="calculator-config">.
 *
 * Nothing is sent anywhere. The inputs are mirrored into the query string
 * only so a result can be bookmarked or shared as a link.
 */
import { compareSplits, planIncome, roundUp } from './calculators/formulas.js';

function readConfig() {
    const element = document.getElementById('calculator-config');

    return element ? JSON.parse(element.textContent) : null;
}

function formatters(config) {
    const money = new Intl.NumberFormat(config.locale, {
        style: 'currency',
        currency: config.currency,
        maximumFractionDigits: 0,
    });
    const count = new Intl.NumberFormat(config.locale, { maximumFractionDigits: 1 });
    const share = new Intl.NumberFormat(config.locale, { style: 'percent', maximumFractionDigits: 1 });

    return {
        money: (value) => money.format(Math.round(value)),
        count: (value, decimals = 0) => count.format(roundUp(value, decimals)),
        share: (value) => share.format(value),
    };
}

/**
 * A finite, non-negative number from an input, or null when it holds none.
 */
function numberFrom(input) {
    if (!input || input.value.trim() === '') {
        return null;
    }

    const value = Number(input.value);

    return Number.isFinite(value) && value >= 0 ? value : null;
}

function writeResults(root, values) {
    for (const element of root.querySelectorAll('[data-result]')) {
        const key = element.dataset.result;

        element.textContent = key in values ? values[key] : '—';
    }
}

function rememberInputs(form) {
    const params = new URLSearchParams();

    for (const input of form.querySelectorAll('input[name]:not([data-mirror])')) {
        if (input.value.trim() !== '') {
            params.set(input.name, input.value);
        }
    }

    const query = params.toString();
    history.replaceState(history.state, '', query ? `?${query}` : window.location.pathname);
}

function restoreInputs(form) {
    const params = new URLSearchParams(window.location.search);

    for (const input of form.querySelectorAll('input[name]:not([data-mirror])')) {
        if (params.has(input.name)) {
            input.value = params.get(input.name);
        }
    }
}

function bindIncomePlanner(form, config) {
    const format = formatters(config);
    const results = document.querySelector('[data-results="income-planner"]');
    const income = form.elements.income;
    const slider = form.elements.income_range;

    const render = () => {
        const desiredIncome = numberFrom(income);
        results.toggleAttribute('data-empty', desiredIncome === null || desiredIncome === 0);

        if (desiredIncome === null || desiredIncome === 0) {
            writeResults(results, {});

            return;
        }

        const plan = planIncome(desiredIncome, config);
        const values = {
            desiredIncome: format.money(plan.desiredIncome),
            businessExpenses: format.money(plan.businessExpenses),
            companySplit: format.money(plan.companySplit),
            royalty: format.money(plan.royalty),
            fees: format.money(plan.fees),
            grossCommission: format.money(plan.grossCommission),
            averageCommission: format.money(plan.averageCommission),
            appointmentsPerWeek: format.count(plan.appointmentsPerWeek, 1),
            conversationsPerDay: format.count(plan.conversationsPerDay, 1),
        };

        for (const [key, perYear] of Object.entries(plan.yearly)) {
            values[`${key}.year`] = format.count(perYear);
            values[`${key}.month`] = format.count(perYear / 12, 1);
            values[`${key}.week`] = format.count(perYear / plan.workingWeeks, 1);
        }

        writeResults(results, values);
    };

    income.addEventListener('input', () => {
        const value = numberFrom(income);

        if (value !== null) {
            slider.value = String(value);
        }

        render();
        rememberInputs(form);
    });

    slider.addEventListener('input', () => {
        income.value = slider.value;
        render();
        rememberInputs(form);
    });

    for (const button of form.querySelectorAll('[data-preset]')) {
        button.addEventListener('click', () => {
            income.value = button.dataset.preset;
            slider.value = button.dataset.preset;
            render();
            rememberInputs(form);
        });
    }

    restoreInputs(form);
    slider.value = income.value;
    render();
}

function bindSplitComparison(form, config) {
    const format = formatters(config);
    const results = document.querySelector('[data-results="split-comparison"]');
    const headline = results.querySelector('[data-headline]');
    const bars = results.querySelectorAll('[data-bar]');

    const render = () => {
        const inputs = {
            salesVolume: numberFrom(form.elements.sales_volume),
            commissionPercent: numberFrom(form.elements.commission_percent),
            splitPercent: numberFrom(form.elements.split_percent),
            royaltyPercent: numberFrom(form.elements.royalty_percent),
        };
        const percentsValid = [inputs.commissionPercent, inputs.splitPercent, inputs.royaltyPercent].every(
            (value) => value !== null && value <= 100,
        );
        const isComplete = inputs.salesVolume !== null && inputs.salesVolume > 0 && percentsValid;

        results.toggleAttribute('data-empty', !isComplete);

        if (!isComplete) {
            writeResults(results, {});
            delete results.dataset.tone;
            headline.textContent = headline.dataset.emptyText;

            for (const bar of bars) {
                bar.style.width = '0%';
            }

            return;
        }

        const comparison = compareSplits(inputs, config);
        const values = { grossCommission: format.money(comparison.grossCommission) };

        for (const side of ['current', 'company']) {
            const plan = comparison[side];
            values[`${side}.royalty`] = format.money(plan.royalty);
            values[`${side}.companySplit`] = format.money(plan.companySplit);
            values[`${side}.fees`] = format.money(plan.fees);
            values[`${side}.takeHome`] = format.money(plan.takeHome);
            values[`${side}.keptShare`] = format.share(plan.keptShare);
        }

        const difference = Math.round(comparison.difference);
        values.difference = format.money(Math.abs(difference));
        values.projectedDifference = format.money(Math.abs(comparison.projectedDifference));

        const tone = difference > 0 ? 'more' : difference < 0 ? 'less' : 'same';
        results.dataset.tone = tone;
        headline.textContent = headline.dataset[`${tone}Text`].replace(':amount', values.difference);

        writeResults(results, values);

        for (const bar of bars) {
            bar.style.width = `${Math.max(0, comparison[bar.dataset.bar].keptShare) * 100}%`;
        }
    };

    form.addEventListener('input', () => {
        render();
        rememberInputs(form);
    });

    restoreInputs(form);
    render();
}

function boot() {
    const config = readConfig();

    if (!config) {
        return;
    }

    for (const form of document.querySelectorAll('form[data-calculator]:not([data-bound])')) {
        form.dataset.bound = '';
        form.addEventListener('submit', (event) => event.preventDefault());

        if (form.dataset.calculator === 'income-planner') {
            bindIncomePlanner(form, config);
        } else if (form.dataset.calculator === 'split-comparison') {
            bindSplitComparison(form, config);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

// The header brand link navigates with Livewire; a page reached that way
// replaces the body without reloading this module, so bind again.
document.addEventListener('livewire:navigated', boot);
