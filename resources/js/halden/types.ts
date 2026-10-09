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
    id: number;
    from: 'team' | 'advisor' | 'notice' | 'dropped';
    who: string;
    body: string;
    reason?: string | null;
    at: string | null;
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
}

export interface Results {
    story: { title: string; paragraphs: string[]; band: string };
    pnl: { name: string; last: number; now: number; total?: boolean }[];
    named: { name: string; amount: number; why: string }[];
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
    }[];
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
    };
    memo: { text: string; savedAt: string | null };
    ready: string | null;
    results: Results | null;
    feedback: { title: string; text: string; at: string } | null;
}

export interface LeverText {
    pages: Record<string, { title: string; intro: string }>;
    help: Record<string, string>;
    choices: Record<string, Record<string, string>>;
    badges: Record<string, string>;
    station_notes: Record<string, string>;
    fixed_items: Record<
        string,
        { title: string; badge: string; text: string }[]
    >;
}
