export type DecisionValue = string | number | null;
export type DecisionMap = Record<string, DecisionValue>;

export interface Lever {
    key: string;
    page: string;
    label: string;
    unit: string;
    min: number | null;
    max: number | null;
    step: number | null;
    choices: string[];
    default: string;
    unlock: number;
    tier: string;
    isOpen: boolean;
    isNew: boolean;
}

export interface QuarterInfo {
    id: number;
    number: number;
    total: number;
    label: string;
    status: 'upcoming' | 'open' | 'closed' | 'published';
    deadline: string | null;
    deadlineText: string;
}

export interface QuarterLink {
    id: number;
    number: number;
    label: string;
    status: string;
}

export interface Briefing {
    dateline: string;
    headline: string;
    paragraphs: string[];
    question: string;
}

export interface QuarterContent {
    briefing: Briefing;
    rule: string;
    newPagesNote: string;
    marchetti: string;
    rotationNote: string | null;
    exhibits: { title: string; url: string }[];
}

export interface MarketRow {
    name: string;
    last: number | null;
    now: number;
    unit: 'usd' | 'rate';
    what: string;
}

export interface Advisor {
    key: string;
    initials: string;
    name: string;
    role: string;
    good_at: string;
    watch_out: string;
}

export interface AdvisorMessageView {
    id: number | string;
    from: 'team' | 'advisor' | 'notice' | 'dropped';
    who: string;
    body: string;
    reason?: string | null;
    invited?: string[];
    at: string | null;
}

export interface MeetingView {
    messages: AdvisorMessageView[];
    invited: string[];
    min: number;
    max: number;
}

export interface AdvisorCard extends Advisor {
    first: string;
    used: number;
    limit: number;
    left: number;
    messages: AdvisorMessageView[];
}

export interface AdvisorsView {
    enabled: boolean;
    open: boolean;
    text: Record<string, string>;
    cards: AdvisorCard[];
    meeting: MeetingView;
}

export interface Results {
    story: { title: string; paragraphs: string[]; band: string };
    pnl: { name: string; last: number; now: number; total?: boolean }[];
    named: { name: string; amount: number | null; why: string }[];
    bridge: {
        previous: number;
        now: number;
        parts: { name: string; note: string; value: number }[];
    };
    kpis: {
        name: string;
        weight: string;
        unit: string;
        def: string;
        last: number | null;
        now: number;
        class: { low: number; avg: number; high: number } | null;
    }[];
    compare: boolean;
    score: number;
    scoreLast: number | null;
    rank: number;
    rankLast: number | null;
    earlier: { when: string; text: string }[];
    news: { title: string; body: string }[];
    relations: { who: string; was: string; now: string; why: string }[];
    money: { fcf: number; netDebt: number; capex: number; tax: number };
}

export interface PlayProps {
    mandate: string;
    readOnly: boolean;
    canEdit: boolean;
    startPage: string;
    team: {
        id: number;
        name: string;
        firstMeeting: string | null;
        strategy: string | null;
        members: { name: string; seat: string; isMe: boolean }[];
    };
    me: { seat: string; seatLabel: string } | null;
    section: { name: string; teamCount: number };
    quarter: QuarterInfo;
    quarters: QuarterLink[];
    content: QuarterContent | null;
    market: MarketRow[];
    wti: { label: string; value: number; current: boolean }[];
    advisors: AdvisorsView;
    leverText: LeverText;
    pages: string[];
    decisions: {
        previous: DecisionMap;
        current: DecisionMap;
        saved: DecisionMap;
        savedPages: Record<string, { by: string; at: string }>;
        levers: Lever[];
    };
    desk: {
        permianNow: number | null;
        decline: number;
        adds: number[];
        brCapacity: number;
        rotCapacity: number;
        rotStatus: string | null;
        genevaMaxVolume: number;
        marketTp: number | null;
        costTp: number;
        capital: {
            envelope: number;
            rate: number;
            projects: { key: string; label: string; outlay: number }[];
            committedBefore: string[];
            capacityMatchedBefore: boolean;
        } | null;
        rival: {
            cut: number;
            clusters: { key: string; label: string; gallons: number }[];
        } | null;
        opec: {
            days: Record<string, number>;
            wti: number;
            carryRate: number;
        } | null;
        rebrand: {
            regions: {
                key: string;
                label: string;
                sites: number;
                keep: number;
                halden: number;
                cost: number;
                rebrandedBefore: boolean;
            }[];
        } | null;
        kessana: {
            open: boolean;
            take: number;
            exited: boolean;
            volume: number;
            takes: Record<string, number>;
            exitValue: number;
            bookValue: number;
        } | null;
        labor: {
            open: boolean;
            wageBill: number;
            demand: number;
            half: number;
            taxRate: number;
            uplift: number;
            peakCost: number;
            offPeakCost: number;
            outageChance: number;
            outageCost: number;
            healthPenalty: number;
            turnaroundBefore: string;
            pending: boolean;
            markets: {
                market: string;
                structure: string;
                wage_k: number;
                note: string;
            }[];
        } | null;
        portfolio: {
            open: boolean;
            envelope: number;
            floor: number;
            discretionary: number;
            years: number;
            carbon: number;
            rotterdamClosed: boolean;
            buckets: { key: string; label: string; ceiling: number }[];
            projects: {
                key: string;
                label: string;
                bucket: string;
                bucketLabel: string;
                cost: number;
                available: boolean;
                before: string;
            }[];
        } | null;
    };
    memo: { text: string; savedAt: string | null };
    ready: string | null;
    results: Results | null;
    board: {
        defense: {
            title: string;
            intro: string;
            parts: {
                key: string;
                title: string;
                prompt: string;
                words: number;
            }[];
            sentence_title: string;
            sentence_note: string;
            no_sentence: string;
        };
        world: {
            title: string;
            text: string;
            carbon: string;
            demand: string;
            value: number;
        };
        sentence: string | null;
        record: {
            number: number;
            label: string;
            question: string;
            ebitda: number;
            score: number | null;
            rank: number | null;
            memo: string;
        }[];
        submission: {
            parts: Record<string, string>;
            savedAt: string | null;
            savedBy: string | null;
        };
        verdict: {
            heading: string;
            intro: string;
            ending: string;
            published: boolean;
            title: string;
            paragraphs: string[];
        } | null;
    } | null;
    feedback: { title: string; text: string; at: string } | null;
    carrying: {
        title: string;
        text: string | null;
        reason: string | null;
    } | null;
    help: {
        enabled: boolean;
        screen: Record<string, string>;
        faq: { q: string; a: string }[];
    };
}

export interface LeverText {
    pages: Record<
        string,
        {
            title: string;
            intro: string;
            rival?: { title: string; intro: string; means: string };
            rebrand?: { title: string; intro: string; means: string };
        }
    >;
    help: Record<string, string>;
    choices: Record<string, Record<string, string>>;
    capacity_matched: string;
    opec_means: string;
    opec_means_none: string;
    rebranded: string;
    kessana: {
        fixed: { title: string; badge: string; text: string };
        settled: { title: string; badge: string; text: string };
        exited: { title: string; badge: string; text: string };
        means: Record<string, string>;
    };
    labor: {
        norway_means: Record<string, string>;
        turnaround_means: Record<string, string>;
        settled_norway: string;
        settled_turnaround_now: string;
        settled_turnaround_wait: string;
    };
    portfolio: {
        intro: string;
        old_list_closed: string;
        under_way: string;
        not_taken: string;
        going: string;
        sold: string;
        held: string;
        not_possible: string;
        means: string;
        means_none: string;
        sale_note: string;
        over: string;
    };
    badges: Record<string, string>;
    station_notes: Record<string, string>;
    fixed_items: Record<
        string,
        { title: string; badge: string; text: string }[]
    >;
}
