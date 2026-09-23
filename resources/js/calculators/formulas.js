/**
 * The arithmetic behind both agent calculators, as pure functions.
 *
 * Nothing here touches the page: every function takes plain numbers plus the
 * config object from config/calculators.php (handed to the browser as JSON)
 * and returns plain numbers. Percentages arrive as percentages (85 means 85%)
 * and are divided down here, in one place.
 */

const percent = (value) => Number(value) / 100;

const capAt = (amount, cap) => (cap === null || cap === undefined ? amount : Math.min(amount, Number(cap)));

/**
 * What an agent keeps from a year's gross commission income under one plan.
 *
 * The royalty (franchise fee) comes off the gross first, the company split is
 * taken from what remains, and flat annual fees come off last. Either cut
 * stops growing at its cap, when the plan has one.
 *
 * @param {number} grossCommission
 * @param {{agent_split_percent: number, royalty_percent: number, company_dollar_cap?: number|null, royalty_cap?: number|null, annual_fees?: number}} plan
 */
export function agentTakeHome(grossCommission, plan) {
    const royalty = capAt(grossCommission * percent(plan.royalty_percent), plan.royalty_cap);
    const companySplit = capAt(
        (grossCommission - royalty) * (1 - percent(plan.agent_split_percent)),
        plan.company_dollar_cap,
    );
    const fees = Number(plan.annual_fees ?? 0);
    const takeHome = grossCommission - royalty - companySplit - fees;

    return {
        grossCommission,
        royalty,
        companySplit,
        fees,
        takeHome,
        keptShare: grossCommission > 0 ? takeHome / grossCommission : 0,
    };
}

/**
 * The gross commission income a plan needs to leave `targetTakeHome` in the
 * agent's pocket.
 *
 * Solved by bisection rather than inverted by hand, because caps make the
 * take-home curve piecewise: whatever caps or fees the config adds, take-home
 * never falls as gross rises, so bisection always converges. Returns Infinity
 * for a plan that could never reach the target (a 0% split with no cap).
 */
export function requiredGrossCommission(targetTakeHome, plan) {
    if (targetTakeHome <= agentTakeHome(0, plan).takeHome) {
        return 0;
    }

    let low = 0;
    let high = Math.max(targetTakeHome, 1);

    while (agentTakeHome(high, plan).takeHome < targetTakeHome) {
        high *= 2;

        if (high > 1e12) {
            return Infinity;
        }
    }

    for (let step = 0; step < 100 && high - low > 0.001; step++) {
        const middle = (low + high) / 2;

        if (agentTakeHome(middle, plan).takeHome < targetTakeHome) {
            low = middle;
        } else {
            high = middle;
        }
    }

    return high;
}

/**
 * Calculator 1: work back from a desired take-home income to the business a
 * prospective agent needs under the company's plan.
 *
 * Every count is left fractional so rounding never compounds down the chain;
 * the page rounds each figure up only when it displays it.
 *
 * @param {number} desiredIncome
 * @param {object} config The whole config/calculators.php array.
 */
export function planIncome(desiredIncome, config) {
    const planner = config.income_planner;
    const market = config.market;

    const businessExpenses = Number(planner.annual_business_expenses);
    const brokerage = agentTakeHome(
        requiredGrossCommission(desiredIncome + businessExpenses, config.company_plan),
        config.company_plan,
    );

    const listingShare = percent(planner.listing_share_percent);
    const listingSideCommission = market.average_sale_price * percent(market.listing_commission_percent);
    const buyerSideCommission = market.average_sale_price * percent(market.buyer_commission_percent);
    const averageCommission = listingShare * listingSideCommission + (1 - listingShare) * buyerSideCommission;

    const closings = brokerage.grossCommission / averageCommission;
    const listingsSold = closings * listingShare;
    const buyerClosings = closings * (1 - listingShare);
    const listingsTaken = listingsSold / percent(planner.listing_sold_percent);
    const buyerClients = buyerClosings / percent(planner.buyer_close_percent);
    const listingAppointments = listingsTaken / percent(planner.listing_appointment_percent);
    const buyerConsultations = buyerClients / percent(planner.buyer_consultation_percent);
    const appointments = listingAppointments + buyerConsultations;
    const conversations = appointments * Number(planner.conversations_per_appointment);

    const weeks = Number(planner.working_weeks);
    const days = weeks * Number(planner.working_days_per_week);

    return {
        desiredIncome,
        businessExpenses,
        grossCommission: brokerage.grossCommission,
        royalty: brokerage.royalty,
        companySplit: brokerage.companySplit,
        fees: brokerage.fees,
        averageCommission,
        yearly: {
            closings,
            listingsSold,
            buyerClosings,
            listingsTaken,
            buyerClients,
            listingAppointments,
            buyerConsultations,
            appointments,
            conversations,
        },
        appointmentsPerWeek: appointments / weeks,
        conversationsPerDay: conversations / days,
        workingWeeks: weeks,
    };
}

/**
 * Calculator 2: an active agent's current take-home beside what the same
 * production would pay under the company's plan.
 *
 * The current brokerage is modelled from the four figures the agent enters,
 * with no caps: the agent is not asked for them, and assuming none keeps the
 * comparison from flattering the company's plan with a cap they may not have.
 *
 * @param {{salesVolume: number, commissionPercent: number, splitPercent: number, royaltyPercent: number}} inputs
 * @param {object} config The whole config/calculators.php array.
 */
export function compareSplits(inputs, config) {
    const grossCommission = inputs.salesVolume * percent(inputs.commissionPercent);

    const current = agentTakeHome(grossCommission, {
        agent_split_percent: inputs.splitPercent,
        royalty_percent: inputs.royaltyPercent,
        company_dollar_cap: null,
        royalty_cap: null,
        annual_fees: 0,
    });
    const company = agentTakeHome(grossCommission, config.company_plan);
    const difference = company.takeHome - current.takeHome;
    const years = Number(config.split_comparison.projection_years);

    return {
        grossCommission,
        current,
        company,
        difference,
        projectionYears: years,
        projectedDifference: difference * years,
    };
}

/**
 * Round up to `decimals` places, for "you need at least this many" figures.
 *
 * Nudged through toFixed first so floating-point noise (5.0000000001) does
 * not round a whole number up to the next one.
 */
export function roundUp(value, decimals = 0) {
    const factor = 10 ** decimals;

    return Math.ceil(Number((value * factor).toFixed(6))) / factor;
}
