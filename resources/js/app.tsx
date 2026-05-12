import './bootstrap';
import '../css/app.css';

import { QueryClient, QueryClientProvider, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import {
    Activity,
    BarChart3,
    CalendarDays,
    ChevronRight,
    Gauge,
    Home,
    KeyRound,
    ListPlus,
    RefreshCw,
    ReceiptText,
    Settings,
    Shield,
    Trophy,
    Users,
    WalletCards,
} from 'lucide-react';
import React, { FormEvent, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Link, NavLink, Route, BrowserRouter as Router, Routes, useParams } from 'react-router-dom';

type League = {
    id: number;
    name: string;
    country?: string | null;
    season?: string | null;
    teams_count?: number;
    matches_count?: number;
};

type Team = {
    id: number;
    league_id?: number | null;
    name: string;
    short_name?: string | null;
    country?: string | null;
    league?: League | null;
};

type Match = {
    id: number;
    league_id: number;
    home_team_id: number;
    away_team_id: number;
    starts_at: string;
    status: 'scheduled' | 'finished' | 'cancelled';
    home_goals?: number | null;
    away_goals?: number | null;
    notes?: string | null;
    league?: League;
    home_team?: Team;
    away_team?: Team;
    analysis_results?: PersistedAnalysis[];
};

type PersistedAnalysis = {
    id: number;
    result_status?: string | null;
    evaluated_at?: string | null;
    evaluation_reason?: string | null;
    suggested_market?: string | null;
    suggested_selection?: string | null;
    confidence?: number | null;
    risk_level?: string | null;
};

type FormWindow = {
    games: number;
    wins: number;
    draws: number;
    losses: number;
    goals_for: number;
    goals_against: number;
    avg_goals_for: number;
    avg_goals_against: number;
    goal_difference_avg: number;
    win_rate: number;
    non_loss_rate: number;
    over_1_5_rate: number;
    over_2_5_rate: number;
    both_teams_score_rate: number;
    scored_at_least_one_rate: number;
    conceded_at_least_one_rate: number;
};

type TeamForm = {
    team_id: number;
    team_name: string;
    sample_size: number;
    last_5: FormWindow;
    last_10: FormWindow;
    home: FormWindow;
    away: FormWindow;
};

type Suggestion = {
    market: string;
    selection: string;
    score: number;
    confidence: number;
    confidence_label?: string;
    risk_level: string;
    reasons: string[];
};

type Analysis = {
    match_id: number;
    analysis_result_id: number;
    league: string;
    home_team: string;
    away_team: string;
    suggested_market: string;
    suggested_selection: string;
    confidence: number;
    risk_level: string;
    result_status: string;
    evaluated_at?: string | null;
    evaluation_reason?: string | null;
    summary: string;
    factors: string[];
    metrics: {
        home_team_form: TeamForm;
        away_team_form: TeamForm;
    };
    suggestions: Suggestion[];
    sample_warnings: string[];
    generated_at?: string;
};

type PerformanceGroup = {
    label: string;
    total: number;
    won: number;
    lost: number;
    win_rate: number;
};

type Performance = {
    total_analyses: number;
    evaluated_analyses: number;
    pending_analyses: number;
    won: number;
    lost: number;
    void: number;
    unknown: number;
    win_rate: number;
    average_confidence: number;
    win_rate_by_market: PerformanceGroup[];
    win_rate_by_risk_level: PerformanceGroup[];
    win_rate_by_confidence_band: PerformanceGroup[];
};

type AnalysisHistoryItem = {
    id: number;
    match: { id: number; status: string };
    league?: League | null;
    home_team?: Team | null;
    away_team?: Team | null;
    starts_at?: string | null;
    suggested_market: string;
    suggested_selection: string;
    confidence?: number | null;
    risk_level?: string | null;
    result_status?: string | null;
    summary: string;
    generated_at?: string | null;
    evaluated_at?: string | null;
    evaluation_reason?: string | null;
    match_score?: { home_goals: number; away_goals: number; label: string } | null;
};

type AnalysisRule = {
    id?: number | null;
    key: string;
    name: string;
    description?: string | null;
    type: 'number' | 'integer' | 'boolean';
    value: number | boolean;
    weight: number;
    is_active: boolean;
    config: {
        type: string;
        value: number | boolean;
        default: number | boolean;
    };
};

type BacktestItem = {
    match_id: number;
    league: string;
    home_team: string;
    away_team: string;
    starts_at: string;
    match_score: string;
    suggested_market: string;
    suggested_selection: string;
    score: number;
    confidence: number;
    risk_level: string;
    result_status: string;
    evaluation_reason: string;
};

type BacktestSummary = {
    total_matches: number;
    evaluated: number;
    won: number;
    lost: number;
    void: number;
    unknown: number;
    pending: number;
    win_rate: number;
    average_confidence: number;
};

type BacktestResult = {
    summary: BacktestSummary;
    by_market: Array<BacktestSummary & { label: string }>;
    by_risk_level: Array<BacktestSummary & { label: string }>;
    by_confidence_band: Array<BacktestSummary & { label: string }>;
    items: BacktestItem[];
};

type ImportRow = {
    line?: number | null;
    status: 'valid' | 'duplicate' | 'invalid' | 'skipped';
    action: string;
    match_id?: number | null;
    errors: string[];
    data: {
        date?: string;
        league?: string;
        home_team?: string;
        away_team?: string;
        status?: string;
        home_goals?: number | null;
        away_goals?: number | null;
    };
};

type ImportResult = {
    summary: {
        total_rows: number;
        valid_rows: number;
        invalid_rows: number;
        duplicates: number;
        created: number;
        skipped: number;
        mode: string;
    };
    rows: ImportRow[];
};

type ApiIntegration = {
    provider: string;
    name: string;
    description: string;
    status: 'ready' | 'planned' | 'available';
    is_active: boolean;
    configured: boolean;
    key_preview?: string | null;
    config: {
        league?: number;
        competition?: string;
        season?: number;
        timezone?: string;
    };
    last_synced_at?: string | null;
    last_sync_summary?: {
        total_rows?: number;
        created?: number;
        updated?: number;
        skipped?: number;
    } | null;
};

type ApiSyncResult = {
    summary: {
        source: string;
        total_rows: number;
        created: number;
        updated: number;
        skipped: number;
    };
    rows: ImportRow[];
};

type Bookmaker = {
    id: number;
    name: string;
    country?: string | null;
};

type Bankroll = {
    id: number;
    bookmaker?: Bookmaker | null;
    name: string;
    initial_balance: number;
    current_balance: number;
    profit: number;
    roi: number;
    currency: string;
    bets_count: number;
    settled_bets_count: number;
    notes?: string | null;
};

type Bet = {
    id: number;
    bankroll?: { id: number; name: string; currency: string };
    bookmaker?: Bookmaker | null;
    match?: { id: number; label: string; starts_at?: string | null; league?: League | null } | null;
    placed_at: string;
    market: string;
    selection: string;
    stake: number;
    odds: number;
    status: 'pending' | 'won' | 'lost' | 'void' | 'cashed_out';
    payout?: number | null;
    profit: number;
    notes?: string | null;
};

const queryClient = new QueryClient();
const api = axios.create({ baseURL: '/api' });

const betMarkets: [string, string][] = [
    ['Mais de 1.5 gols', 'Mais de 1.5 gols'],
    ['Mais de 2.5 gols', 'Mais de 2.5 gols'],
    ['Ambas marcam', 'Ambas marcam'],
    ['Dupla chance mandante', 'Dupla chance mandante'],
    ['Dupla chance visitante', 'Dupla chance visitante'],
    ['Vitoria mandante', 'Vitoria mandante'],
    ['Vitoria visitante', 'Vitoria visitante'],
];

function betSelectionOptions(market: string, match?: Match): [string, string][] {
    const home = match?.home_team?.name ?? 'Mandante';
    const away = match?.away_team?.name ?? 'Visitante';
    const options: Record<string, [string, string][]> = {
        'Mais de 1.5 gols': [['Mais de 1.5', 'Mais de 1.5']],
        'Mais de 2.5 gols': [['Mais de 2.5', 'Mais de 2.5']],
        'Ambas marcam': [['Sim', 'Sim']],
        'Dupla chance mandante': [[`${home} ou Empate`, `${home} ou Empate`]],
        'Dupla chance visitante': [[`${away} ou Empate`, `${away} ou Empate`]],
        'Vitoria mandante': [[`${home} vence`, `${home} vence`]],
        'Vitoria visitante': [[`${away} vence`, `${away} vence`]],
    };

    return options[market] ?? [];
}

function suggestedMarketLabel(market?: string | null) {
    return {
        'Over 1.5 Goals': 'Mais de 1.5 gols',
        'Over 2.5 Goals': 'Mais de 2.5 gols',
        'Both Teams To Score': 'Ambas marcam',
        'Home Double Chance': 'Dupla chance mandante',
        'Away Double Chance': 'Dupla chance visitante',
        'Home Win Lean': 'Vitoria mandante',
        'Away Win Lean': 'Vitoria visitante',
    }[market ?? ''] ?? market ?? '-';
}

function suggestedSelectionLabel(market?: string | null, selection?: string | null) {
    if (!selection) return '-';

    if (market === 'Both Teams To Score' && selection === 'Yes') {
        return 'Sim';
    }

    return selection
        .replace('Over 1.5', 'Mais de 1.5')
        .replace('Over 2.5', 'Mais de 2.5');
}

function useApiQuery<T>(key: readonly unknown[], url: string) {
    return useQuery({ queryKey: key, queryFn: async () => (await api.get<T>(url)).data });
}

function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <Router>
                <div className="min-h-screen bg-[#0a0f1a]">
                    <aside className="fixed inset-y-0 left-0 hidden w-64 border-r border-white/10 bg-[#0d1422] p-5 lg:block">
                        <div className="mb-8 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-400 text-slate-950">
                                <Activity size={22} />
                            </div>
                            <div>
                                <p className="text-lg font-semibold">FutIA Local</p>
                                <p className="text-xs text-slate-400">Analise estatistica</p>
                            </div>
                        </div>
                        <nav className="space-y-1">
                            <NavItem to="/" icon={<Home size={18} />} label="Dashboard" />
                            <NavItem to="/leagues" icon={<Trophy size={18} />} label="Ligas" />
                            <NavItem to="/teams" icon={<Users size={18} />} label="Times" />
                            <NavItem to="/matches" icon={<CalendarDays size={18} />} label="Partidas" />
                            <NavItem to="/analysis" icon={<BarChart3 size={18} />} label="Analises" />
                            <NavItem to="/analysis-history" icon={<Gauge size={18} />} label="Historico" />
                            <NavItem to="/analysis-rules" icon={<ListPlus size={18} />} label="Regras" />
                            <NavItem to="/backtesting" icon={<BarChart3 size={18} />} label="Backtesting" />
                            <NavItem to="/imports" icon={<ListPlus size={18} />} label="Importar" />
                            <NavItem to="/integrations" icon={<Settings size={18} />} label="Integracoes" />
                            <NavItem to="/bankroll" icon={<WalletCards size={18} />} label="Banca" />
                        </nav>
                    </aside>
                    <main className="lg:pl-64">
                        <header className="sticky top-0 z-10 border-b border-white/10 bg-[#0a0f1a]/90 px-5 py-4 backdrop-blur lg:hidden">
                            <div className="mb-3 flex items-center gap-2 font-semibold">
                                <Activity className="text-emerald-300" size={20} />
                                FutIA Local
                            </div>
                            <div className="flex gap-2 overflow-x-auto">
                                <TopLink to="/" label="Dashboard" />
                                <TopLink to="/leagues" label="Ligas" />
                                <TopLink to="/teams" label="Times" />
                                <TopLink to="/matches" label="Partidas" />
                                <TopLink to="/analysis" label="Analises" />
                                <TopLink to="/analysis-history" label="Historico" />
                                <TopLink to="/analysis-rules" label="Regras" />
                                <TopLink to="/backtesting" label="Backtesting" />
                                <TopLink to="/imports" label="Importar" />
                                <TopLink to="/integrations" label="Integracoes" />
                                <TopLink to="/bankroll" label="Banca" />
                            </div>
                        </header>
                        <div className="mx-auto max-w-7xl px-5 py-8">
                            <Routes>
                                <Route path="/" element={<Dashboard />} />
                                <Route path="/leagues" element={<LeaguesPage />} />
                                <Route path="/teams" element={<TeamsPage />} />
                                <Route path="/matches" element={<MatchesPage />} />
                                <Route path="/matches/:id" element={<MatchDetailsPage />} />
                                <Route path="/analysis" element={<AnalysisPage />} />
                                <Route path="/analysis-history" element={<AnalysisHistoryPage />} />
                                <Route path="/analysis-rules" element={<AnalysisRulesPage />} />
                                <Route path="/backtesting" element={<BacktestingPage />} />
                                <Route path="/imports" element={<ImportsPage />} />
                                <Route path="/integrations" element={<IntegrationsPage />} />
                                <Route path="/bankroll" element={<BankrollPage />} />
                            </Routes>
                        </div>
                    </main>
                </div>
            </Router>
        </QueryClientProvider>
    );
}

function NavItem({ to, icon, label }: { to: string; icon: React.ReactNode; label: string }) {
    return (
        <NavLink
            to={to}
            end={to === '/'}
            className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition ${
                    isActive ? 'bg-emerald-400/15 text-emerald-200' : 'text-slate-300 hover:bg-white/5 hover:text-white'
                }`
            }
        >
            {icon}
            {label}
        </NavLink>
    );
}

function TopLink({ to, label }: { to: string; label: string }) {
    return (
        <NavLink
            to={to}
            end={to === '/'}
            className={({ isActive }) =>
                `rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-emerald-400/15 text-emerald-200' : 'bg-white/5 text-slate-300'}`
            }
        >
            {label}
        </NavLink>
    );
}

function PageTitle({ title, subtitle }: { title: string; subtitle: string }) {
    return (
        <div className="mb-7">
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-300">FutIA Local</p>
            <h1 className="mt-2 text-3xl font-semibold text-white">{title}</h1>
            <p className="mt-2 max-w-3xl text-sm text-slate-400">{subtitle}</p>
        </div>
    );
}

function Panel({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return <section className={`rounded-lg border border-white/10 bg-white/[0.035] p-5 shadow-2xl shadow-black/20 ${className}`}>{children}</section>;
}

function Dashboard() {
    const { data, isLoading } = useApiQuery<{
        totals: Record<string, number>;
        upcoming_matches: Match[];
        latest_matches: Match[];
    }>(['dashboard'], '/dashboard');
    const { data: performance } = useApiQuery<Performance>(['analysis-performance'], '/analysis-performance');

    return (
        <>
            <PageTitle title="Dashboard" subtitle="Visao local das ligas, times, partidas e primeiras estruturas de analise." />
            {isLoading ? (
                <Loading />
            ) : (
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric icon={<Trophy />} label="Ligas" value={data?.totals.leagues ?? 0} />
                        <Metric icon={<Users />} label="Times" value={data?.totals.teams ?? 0} />
                        <Metric icon={<CalendarDays />} label="Partidas" value={data?.totals.matches ?? 0} />
                        <Metric icon={<Gauge />} label="Analises/Previsoes" value={data?.totals.analysis_predictions ?? 0} />
                    </div>
                    <div className="grid gap-6 xl:grid-cols-2">
                        <MatchList title="Proximos jogos" matches={data?.upcoming_matches ?? []} />
                        <MatchList title="Ultimas partidas cadastradas" matches={data?.latest_matches ?? []} />
                    </div>
                    {performance && <PerformanceSection performance={performance} />}
                </div>
            )}
        </>
    );
}

function Metric({ icon, label, value }: { icon: React.ReactNode; label: string; value: number }) {
    return (
        <Panel>
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-slate-400">{label}</p>
                    <p className="mt-2 text-3xl font-semibold text-white">{value}</p>
                </div>
                <div className="rounded-lg bg-emerald-400/15 p-3 text-emerald-200">{icon}</div>
            </div>
        </Panel>
    );
}

function PerformanceSection({ performance }: { performance: Performance }) {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Metric icon={<BarChart3 />} label="Analises" value={performance.total_analyses} />
                <Metric icon={<Gauge />} label="Avaliadas" value={performance.evaluated_analyses} />
                <Metric icon={<CalendarDays />} label="Pendentes" value={performance.pending_analyses} />
                <Metric icon={<Trophy />} label="Taxa de acerto" value={performance.win_rate} />
            </div>
            <div className="grid gap-6 xl:grid-cols-3">
                <PerformanceTable title="Por mercado" rows={performance.win_rate_by_market} />
                <PerformanceTable title="Por risco" rows={performance.win_rate_by_risk_level.map((row) => ({ ...row, label: riskLabel(row.label) }))} />
                <PerformanceTable title="Por confianca" rows={performance.win_rate_by_confidence_band} />
            </div>
        </div>
    );
}

function PerformanceTable({ title, rows }: { title: string; rows: PerformanceGroup[] }) {
    return (
        <Panel>
            <h2 className="mb-4 text-lg font-semibold text-white">{title}</h2>
            <div className="space-y-2">
                {rows.map((row) => (
                    <div key={row.label} className="grid grid-cols-[1fr_auto] gap-3 rounded-lg border border-white/10 bg-slate-950/50 p-3 text-sm">
                        <div>
                            <p className="font-medium text-white">{row.label}</p>
                            <p className="text-xs text-slate-500">{row.won} acertos · {row.lost} erros · {row.total} total</p>
                        </div>
                        <p className="font-semibold text-emerald-200">{row.win_rate}%</p>
                    </div>
                ))}
            </div>
        </Panel>
    );
}

function LeaguesPage() {
    const queryClient = useQueryClient();
    const { data = [] } = useApiQuery<League[]>(['leagues'], '/leagues');
    const mutation = useMutation({
        mutationFn: (payload: Partial<League>) => api.post('/leagues', payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['leagues'] }),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        mutation.mutate(Object.fromEntries(form.entries()));
        event.currentTarget.reset();
    }

    return (
        <>
            <PageTitle title="Ligas" subtitle="Cadastre campeonatos e temporadas para organizar times e partidas." />
            <div className="grid gap-6 xl:grid-cols-[360px_1fr]">
                <Panel>
                    <FormTitle icon={<ListPlus />} title="Nova liga" />
                    <form onSubmit={submit} className="space-y-3">
                        <Input name="name" placeholder="Nome da liga" required />
                        <Input name="country" placeholder="Pais" />
                        <Input name="season" placeholder="Temporada" />
                        <Button>Criar liga</Button>
                    </form>
                </Panel>
                <Panel>
                    <DataTable
                        headers={['Liga', 'Pais', 'Temporada', 'Times', 'Partidas']}
                        rows={data.map((league) => [
                            league.name,
                            league.country ?? '-',
                            league.season ?? '-',
                            String(league.teams_count ?? 0),
                            String(league.matches_count ?? 0),
                        ])}
                    />
                </Panel>
            </div>
        </>
    );
}

function TeamsPage() {
    const queryClient = useQueryClient();
    const { data: leagues = [] } = useApiQuery<League[]>(['leagues'], '/leagues');
    const { data: teams = [] } = useApiQuery<Team[]>(['teams'], '/teams');
    const mutation = useMutation({
        mutationFn: (payload: Record<string, FormDataEntryValue>) => api.post('/teams', payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['teams'] }),
    });

    return (
        <>
            <PageTitle title="Times" subtitle="Base inicial de clubes, com vinculo opcional com uma liga." />
            <div className="grid gap-6 xl:grid-cols-[360px_1fr]">
                <Panel>
                    <FormTitle icon={<Shield />} title="Novo time" />
                    <form onSubmit={(event) => submitForm(event, mutation.mutate)} className="space-y-3">
                        <Input name="name" placeholder="Nome do time" required />
                        <Input name="short_name" placeholder="Sigla" />
                        <Input name="country" placeholder="Pais" />
                        <Select name="league_id" options={leagues.map((league) => [String(league.id), league.name])} placeholder="Sem liga" />
                        <Button>Criar time</Button>
                    </form>
                </Panel>
                <Panel>
                    <DataTable
                        headers={['Time', 'Sigla', 'Liga', 'Pais']}
                        rows={teams.map((team) => [team.name, team.short_name ?? '-', team.league?.name ?? '-', team.country ?? '-'])}
                    />
                </Panel>
            </div>
        </>
    );
}

function MatchesPage() {
    const queryClient = useQueryClient();
    const { data: leagues = [] } = useApiQuery<League[]>(['leagues'], '/leagues');
    const { data: teams = [] } = useApiQuery<Team[]>(['teams'], '/teams');
    const { data: matches = [] } = useApiQuery<Match[]>(['matches'], '/matches');
    const mutation = useMutation({
        mutationFn: (payload: Record<string, FormDataEntryValue>) => api.post('/matches', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['matches'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        },
    });

    return (
        <>
            <PageTitle title="Partidas" subtitle="Registre jogos agendados, finalizados ou cancelados para alimentar a futura analise estatistica." />
            <div className="grid gap-6 xl:grid-cols-[420px_1fr]">
                <Panel>
                    <FormTitle icon={<CalendarDays />} title="Nova partida" />
                    <form onSubmit={(event) => submitForm(event, mutation.mutate)} className="space-y-3">
                        <Select name="league_id" options={leagues.map((league) => [String(league.id), league.name])} required placeholder="Selecione a liga" />
                        <Select name="home_team_id" options={teams.map((team) => [String(team.id), team.name])} required placeholder="Time da casa" />
                        <Select name="away_team_id" options={teams.map((team) => [String(team.id), team.name])} required placeholder="Time visitante" />
                        <Input name="starts_at" type="datetime-local" required />
                        <Select name="status" options={[['scheduled', 'Agendada'], ['finished', 'Finalizada'], ['cancelled', 'Cancelada']]} required />
                        <div className="grid grid-cols-2 gap-3">
                            <Input name="home_goals" type="number" min="0" placeholder="Gols casa" />
                            <Input name="away_goals" type="number" min="0" placeholder="Gols fora" />
                        </div>
                        <textarea name="notes" placeholder="Notas" className="min-h-20 w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm outline-none focus:border-emerald-300" />
                        <Button>Criar partida</Button>
                    </form>
                </Panel>
                <MatchList title="Partidas cadastradas" matches={matches} showLinks />
            </div>
        </>
    );
}

function MatchDetailsPage() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    const { data: match } = useApiQuery<Match>(['match', id], `/matches/${id}`);
    const [analysis, setAnalysis] = useState<Analysis | null>(null);
    const mutation = useMutation({
        mutationFn: async () => (await api.get<Analysis>(`/matches/${id}/analysis`)).data,
        onSuccess: setAnalysis,
    });
    const updateMutation = useMutation({
        mutationFn: (payload: Record<string, FormDataEntryValue>) => api.patch(`/matches/${id}`, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['match', id] });
            queryClient.invalidateQueries({ queryKey: ['matches'] });
            queryClient.invalidateQueries({ queryKey: ['analysis-performance'] });
        },
    });
    const latestPersistedAnalysis = match?.analysis_results?.at(-1);

    return (
        <>
            <PageTitle title="Detalhes da partida" subtitle="Dados do jogo e primeira analise estatistica local baseada no historico cadastrado." />
            <div className="grid gap-6 xl:grid-cols-[1fr_440px]">
                <Panel>
                    <p className="text-sm text-slate-400">{match?.league?.name}</p>
                    <div className="mt-4 grid items-center gap-4 sm:grid-cols-[1fr_auto_1fr]">
                        <TeamBlock team={match?.home_team} />
                        <div className="rounded-lg bg-slate-950/70 px-5 py-4 text-center">
                            <p className="text-3xl font-semibold">{score(match)}</p>
                            <p className="mt-1 text-xs uppercase text-slate-500">{match?.status}</p>
                        </div>
                        <TeamBlock team={match?.away_team} alignRight />
                    </div>
                    <p className="mt-6 text-sm text-slate-400">{formatDate(match?.starts_at)}</p>
                    {match?.notes && <p className="mt-3 text-sm text-slate-300">{match.notes}</p>}
                </Panel>
                <Panel>
                    <FormTitle icon={<CalendarDays />} title="Atualizar resultado" />
                    <form key={match?.id} onSubmit={(event) => submitForm(event, updateMutation.mutate)} className="space-y-3">
                        <Select
                            name="status"
                            defaultValue={match?.status ?? 'scheduled'}
                            options={[['scheduled', 'Agendada'], ['finished', 'Finalizada'], ['cancelled', 'Cancelada']]}
                            required
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Input name="home_goals" type="number" min="0" placeholder="Gols casa" defaultValue={match?.home_goals ?? ''} />
                            <Input name="away_goals" type="number" min="0" placeholder="Gols fora" defaultValue={match?.away_goals ?? ''} />
                        </div>
                        <textarea name="notes" placeholder="Notas" defaultValue={match?.notes ?? ''} className="min-h-20 w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm outline-none focus:border-emerald-300" />
                        <Button>Salvar resultado</Button>
                    </form>
                    {latestPersistedAnalysis?.result_status && (
                        <div className="mt-4 rounded-lg border border-white/10 bg-slate-950/50 p-3 text-sm">
                            <p className="text-slate-400">Resultado da analise</p>
                            <p className="mt-1 font-semibold text-white">{statusLabel(latestPersistedAnalysis.result_status)}</p>
                            {latestPersistedAnalysis.evaluation_reason && <p className="mt-2 text-slate-400">{latestPersistedAnalysis.evaluation_reason}</p>}
                            {latestPersistedAnalysis.evaluated_at && <p className="mt-2 text-xs text-slate-500">Avaliado em {formatDate(latestPersistedAnalysis.evaluated_at)}</p>}
                        </div>
                    )}
                </Panel>
                <Panel>
                    <div className="flex items-center justify-between gap-3">
                        <FormTitle icon={<BarChart3 />} title="Analise" />
                        <button onClick={() => mutation.mutate()} className="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300">
                            Gerar analise
                        </button>
                    </div>
                    {analysis ? <AnalysisResultView analysis={analysis} /> : <p className="mt-5 text-sm text-slate-400">Clique em gerar analise para calcular o motor estatistico local.</p>}
                </Panel>
            </div>
            {analysis && (
                <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    <TeamMetricsCard title={analysis.home_team} form={analysis.metrics.home_team_form} side="home" />
                    <TeamMetricsCard title={analysis.away_team} form={analysis.metrics.away_team_form} side="away" />
                    <SuggestionsCard suggestions={analysis.suggestions} />
                </div>
            )}
        </>
    );
}

function AnalysisPage() {
    const [filters, setFilters] = useState({ month: '', league_id: '', team_id: '' });
    const query = new URLSearchParams({ ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')), sort: 'starts_at' }).toString();
    const { data: matches = [] } = useApiQuery<Match[]>(['matches', filters], `/matches${query ? `?${query}` : ''}`);
    const { data: leagues = [] } = useApiQuery<League[]>(['leagues'], '/leagues');
    const { data: teams = [] } = useApiQuery<Team[]>(['teams'], '/teams');

    function updateFilter(event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) {
        setFilters((current) => ({ ...current, [event.target.name]: event.target.value }));
    }

    return (
        <>
            <PageTitle title="Analises" subtitle="Entrada inicial para acompanhar jogos e gerar previsoes estatisticas locais." />
            <Panel className="mb-6">
                <div className="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                    <Input name="month" type="month" value={filters.month} onChange={updateFilter} />
                    <Select
                        name="league_id"
                        value={filters.league_id}
                        onChange={updateFilter}
                        placeholder="Todas as ligas"
                        options={leagues.map((league) => [String(league.id), `${league.name}${league.season ? ` ${league.season}` : ''}`])}
                    />
                    <Select
                        name="team_id"
                        value={filters.team_id}
                        onChange={updateFilter}
                        placeholder="Todos os times"
                        options={teams.map((team) => [String(team.id), team.name])}
                    />
                    <button
                        type="button"
                        onClick={() => setFilters({ month: '', league_id: '', team_id: '' })}
                        className="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/5"
                    >
                        Limpar
                    </button>
                </div>
                <p className="mt-3 text-sm text-slate-400">{matches.length} partidas encontradas para analise.</p>
            </Panel>
            <MatchList title="Partidas disponiveis para analise" matches={matches} showLinks />
        </>
    );
}

function AnalysisHistoryPage() {
    const [filters, setFilters] = useState({ market: '', risk_level: '', result_status: '' });
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '')).toString();
    const { data: analyses = [] } = useApiQuery<AnalysisHistoryItem[]>(['analysis-results', filters], `/analysis-results${query ? `?${query}` : ''}`);

    function updateFilter(event: React.ChangeEvent<HTMLSelectElement>) {
        setFilters((current) => ({ ...current, [event.target.name]: event.target.value }));
    }

    return (
        <>
            <PageTitle title="Historico de Analises" subtitle="Acompanhe previsoes geradas, status de resultado e placares finalizados." />
            <Panel className="mb-6">
                <div className="grid gap-3 md:grid-cols-3">
                    <Select name="market" value={filters.market} onChange={updateFilter} placeholder="Todos os mercados" options={[
                        ['Over 1.5 Goals', 'Over 1.5 Goals'],
                        ['Over 2.5 Goals', 'Over 2.5 Goals'],
                        ['Both Teams To Score', 'Both Teams To Score'],
                        ['Home Double Chance', 'Home Double Chance'],
                        ['Away Double Chance', 'Away Double Chance'],
                        ['Home Win Lean', 'Home Win Lean'],
                        ['Away Win Lean', 'Away Win Lean'],
                    ]} />
                    <Select name="risk_level" value={filters.risk_level} onChange={updateFilter} placeholder="Todos os riscos" options={[
                        ['low', 'Baixo'],
                        ['medium', 'Medio'],
                        ['high', 'Alto'],
                    ]} />
                    <Select name="result_status" value={filters.result_status} onChange={updateFilter} placeholder="Todos os status" options={[
                        ['pending', 'Pendente'],
                        ['won', 'Acertou'],
                        ['lost', 'Errou'],
                        ['void', 'Anulada'],
                        ['unknown', 'Desconhecido'],
                    ]} />
                </div>
            </Panel>
            <Panel>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-left text-sm">
                        <thead className="text-xs uppercase text-slate-500">
                            <tr>
                                <th className="border-b border-white/10 pb-3 font-medium">Data</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Liga</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Jogo</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Mercado</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Conf.</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Risco</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Resultado</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Placar</th>
                                <th className="border-b border-white/10 pb-3 font-medium">Resumo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {analyses.map((item) => (
                                <tr key={item.id} className="border-b border-white/5 last:border-0">
                                    <td className="py-3 text-slate-300">{formatDate(item.starts_at ?? undefined)}</td>
                                    <td className="py-3 text-slate-300">{item.league?.name ?? '-'}</td>
                                    <td className="py-3 text-white">{item.home_team?.name} x {item.away_team?.name}</td>
                                    <td className="py-3 text-slate-300">{item.suggested_market}<br /><span className="text-xs text-emerald-200">{item.suggested_selection}</span></td>
                                    <td className="py-3 text-emerald-200">{item.confidence ?? 0}%</td>
                                    <td className="py-3 text-slate-300">{riskLabel(item.risk_level)}</td>
                                    <td className="py-3 text-slate-300">{statusLabel(item.result_status)}</td>
                                    <td className="py-3 text-slate-300">{item.match_score?.label ?? '-'}</td>
                                    <td className="max-w-sm py-3 text-slate-400">{shortText(item.summary)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
        </>
    );
}

function AnalysisRulesPage() {
    const queryClient = useQueryClient();
    const { data: rules = [] } = useApiQuery<AnalysisRule[]>(['analysis-rules'], '/analysis-rules');
    const mutation = useMutation({
        mutationFn: ({ key, value }: { key: string; value: string | boolean }) => api.patch(`/analysis-rules/${key}`, { value }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['analysis-rules'] });
            queryClient.invalidateQueries({ queryKey: ['analysis-performance'] });
        },
    });

    function updateRule(event: FormEvent<HTMLFormElement>, rule: AnalysisRule) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const rawValue = rule.type === 'boolean' ? form.get('value') === 'true' : String(form.get('value') ?? rule.value);
        mutation.mutate({ key: rule.key, value: rawValue });
    }

    return (
        <>
            <PageTitle title="Regras de Analise" subtitle="Ajuste pesos e parametros usados pelo motor estatistico local. Valores vazios continuam protegidos por defaults do catalogo." />
            <div className="grid gap-4 xl:grid-cols-2">
                {rules.map((rule) => (
                    <Panel key={rule.key}>
                        <div className="mb-4">
                            <p className="text-xs uppercase text-slate-500">{rule.key}</p>
                            <h2 className="mt-1 text-lg font-semibold text-white">{rule.name}</h2>
                            <p className="mt-1 text-sm text-slate-400">{rule.description}</p>
                        </div>
                        <form onSubmit={(event) => updateRule(event, rule)} className="flex items-end gap-3">
                            <div className="flex-1">
                                <p className="mb-1 text-xs text-slate-500">Valor atual</p>
                                {rule.type === 'boolean' ? (
                                    <Select
                                        name="value"
                                        defaultValue={String(rule.value)}
                                        options={[
                                            ['true', 'Ativo'],
                                            ['false', 'Inativo'],
                                        ]}
                                    />
                                ) : (
                                    <Input name="value" type="number" step={rule.type === 'integer' ? '1' : '0.01'} defaultValue={String(rule.value)} />
                                )}
                            </div>
                            <button className="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300">Salvar</button>
                        </form>
                    </Panel>
                ))}
            </div>
        </>
    );
}

function BacktestingPage() {
    const { data: leagues = [] } = useApiQuery<League[]>(['leagues'], '/leagues');
    const [result, setResult] = useState<BacktestResult | null>(null);
    const mutation = useMutation({
        mutationFn: async (payload: Record<string, unknown>) => (await api.post<BacktestResult>('/backtests', payload)).data,
        onSuccess: setResult,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const overrides: Record<string, number | boolean> = {};
        const over15Weight = String(form.get('over_1_5_weight') ?? '');
        const disableHomeWin = form.get('disable_home_win_lean') === 'on';

        if (over15Weight !== '') {
            overrides['market.over_1_5.weight'] = Number(over15Weight);
        }

        if (disableHomeWin) {
            overrides['market.home_win_lean.enabled'] = false;
        }

        const payload: Record<string, unknown> = {
            date_from: form.get('date_from') || undefined,
            date_to: form.get('date_to') || undefined,
            league_id: form.get('league_id') || undefined,
            market: form.get('market') || undefined,
            rule_overrides: overrides,
        };

        mutation.mutate(payload);
    }

    return (
        <>
            <PageTitle title="Backtesting" subtitle="Simule o motor contra partidas finalizadas, usando apenas dados anteriores a cada jogo testado." />
            <Panel className="mb-6">
                <form onSubmit={submit} className="grid gap-3 lg:grid-cols-4">
                    <Input name="date_from" type="date" />
                    <Input name="date_to" type="date" />
                    <Select name="league_id" placeholder="Todas as ligas" options={leagues.map((league) => [String(league.id), league.name])} />
                    <Select name="market" placeholder="Melhor mercado" options={[
                        ['Over 1.5 Goals', 'Over 1.5 Goals'],
                        ['Over 2.5 Goals', 'Over 2.5 Goals'],
                        ['Both Teams To Score', 'Both Teams To Score'],
                        ['Home Double Chance', 'Home Double Chance'],
                        ['Away Double Chance', 'Away Double Chance'],
                        ['Home Win Lean', 'Home Win Lean'],
                        ['Away Win Lean', 'Away Win Lean'],
                    ]} />
                    <Input name="over_1_5_weight" type="number" step="0.05" min="0" placeholder="Simular peso Over 1.5" />
                    <label className="flex items-center gap-2 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-slate-300">
                        <input name="disable_home_win_lean" type="checkbox" className="h-4 w-4" />
                        Desativar Home Win Lean
                    </label>
                    <button className="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300 lg:col-span-2">
                        Rodar backtest
                    </button>
                </form>
            </Panel>
            {mutation.isPending && <Loading />}
            {result && (
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Metric icon={<CalendarDays />} label="Partidas testadas" value={result.summary.total_matches} />
                        <Metric icon={<Trophy />} label="Taxa de acerto" value={result.summary.win_rate} />
                        <Metric icon={<Gauge />} label="Acertos" value={result.summary.won} />
                        <Metric icon={<BarChart3 />} label="Erros" value={result.summary.lost} />
                    </div>
                    <div className="grid gap-6 xl:grid-cols-3">
                        <PerformanceTable title="Backtest por mercado" rows={result.by_market.map(toPerformanceGroup)} />
                        <PerformanceTable title="Backtest por risco" rows={result.by_risk_level.map((row) => toPerformanceGroup({ ...row, label: riskLabel(row.label) }))} />
                        <PerformanceTable title="Backtest por confianca" rows={result.by_confidence_band.map(toPerformanceGroup)} />
                    </div>
                    <Panel>
                        <h2 className="mb-4 text-lg font-semibold text-white">Partidas simuladas</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[920px] text-left text-sm">
                                <thead className="text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="border-b border-white/10 pb-3 font-medium">Data</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Jogo</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Mercado</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Conf.</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Risco</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Resultado</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Placar</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Motivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.items.map((item) => (
                                        <tr key={item.match_id} className="border-b border-white/5 last:border-0">
                                            <td className="py-3 text-slate-300">{formatDate(item.starts_at)}</td>
                                            <td className="py-3 text-white">{item.home_team} x {item.away_team}</td>
                                            <td className="py-3 text-slate-300">{item.suggested_market}<br /><span className="text-xs text-emerald-200">{item.suggested_selection}</span></td>
                                            <td className="py-3 text-emerald-200">{item.confidence}%</td>
                                            <td className="py-3 text-slate-300">{riskLabel(item.risk_level)}</td>
                                            <td className="py-3 text-slate-300">{statusLabel(item.result_status)}</td>
                                            <td className="py-3 text-slate-300">{item.match_score}</td>
                                            <td className="max-w-sm py-3 text-slate-400">{item.evaluation_reason}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>
            )}
        </>
    );
}

function ImportsPage() {
    const queryClient = useQueryClient();
    const [result, setResult] = useState<ImportResult | null>(null);
    const [lastPayload, setLastPayload] = useState<FormData | null>(null);
    const mutation = useMutation({
        mutationFn: async (payload: FormData) => (await api.post<ImportResult>('/imports/matches', payload, { headers: { 'Content-Type': 'multipart/form-data' } })).data,
        onSuccess: (data, payload) => {
            setResult(data);
            setLastPayload(payload);
            queryClient.invalidateQueries({ queryKey: ['matches'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        },
    });

    function submit(event: FormEvent<HTMLFormElement>, mode: 'preview' | 'commit') {
        event.preventDefault();
        const form = event.currentTarget;
        const formData = new FormData(form);
        formData.set('mode', mode);
        mutation.mutate(formData);
    }

    function commitPreview() {
        if (!lastPayload) return;
        const payload = new FormData();
        for (const [key, value] of lastPayload.entries()) {
            payload.append(key, value);
        }
        payload.set('mode', 'commit');
        mutation.mutate(payload);
    }

    return (
        <>
            <PageTitle title="Importar Partidas" subtitle="Carregue CSV ou JSON localmente, revise o preview e grave apenas quando estiver tudo certo." />
            <Panel className="mb-6">
                <form onSubmit={(event) => submit(event, 'preview')} className="space-y-4">
                    <div className="grid gap-3 md:grid-cols-3">
                        <Select name="format" required options={[['csv', 'CSV'], ['json', 'JSON']]} />
                        <Input name="file" type="file" accept=".csv,.json,text/csv,application/json" />
                        <button className="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300">Gerar preview</button>
                    </div>
                    <textarea
                        name="content"
                        placeholder={'Cole CSV ou JSON aqui. CSV esperado: date,league,home_team,away_team,status,home_goals,away_goals'}
                        className="min-h-36 w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm outline-none focus:border-emerald-300"
                    />
                </form>
            </Panel>
            {mutation.isPending && <Loading />}
            {result && (
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <Metric icon={<ListPlus />} label="Linhas" value={result.summary.total_rows} />
                        <Metric icon={<Trophy />} label="Validas" value={result.summary.valid_rows} />
                        <Metric icon={<Gauge />} label="Duplicadas" value={result.summary.duplicates} />
                        <Metric icon={<CalendarDays />} label="Invalidas" value={result.summary.invalid_rows} />
                        <Metric icon={<Shield />} label="Criadas" value={result.summary.created} />
                    </div>
                    {result.summary.mode === 'preview' && result.summary.invalid_rows === 0 && (
                        <button onClick={commitPreview} className="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300">
                            Confirmar importacao
                        </button>
                    )}
                    <Panel>
                        <h2 className="mb-4 text-lg font-semibold text-white">Resultado da importacao</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="border-b border-white/10 pb-3 font-medium">Linha</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Status</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Acao</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Liga</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Jogo</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Data</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Placar</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Erros</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.rows.map((row, index) => (
                                        <tr key={`${row.line}-${index}`} className="border-b border-white/5 last:border-0">
                                            <td className="py-3 text-slate-300">{row.line ?? '-'}</td>
                                            <td className="py-3 text-slate-300">{importStatusLabel(row.status)}</td>
                                            <td className="py-3 text-slate-300">{row.action}</td>
                                            <td className="py-3 text-slate-300">{row.data.league ?? '-'}</td>
                                            <td className="py-3 text-white">{row.data.home_team ?? '-'} x {row.data.away_team ?? '-'}</td>
                                            <td className="py-3 text-slate-300">{row.data.date ?? '-'}</td>
                                            <td className="py-3 text-slate-300">{row.data.home_goals ?? '-'} - {row.data.away_goals ?? '-'}</td>
                                            <td className="max-w-sm py-3 text-slate-400">{row.errors.join(' ') || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>
            )}
        </>
    );
}

function IntegrationsPage() {
    const queryClient = useQueryClient();
    const { data: integrations = [], isLoading } = useApiQuery<ApiIntegration[]>(['integrations'], '/integrations');
    const [syncResult, setSyncResult] = useState<ApiSyncResult | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const apiFootball = integrations.find((integration) => integration.provider === 'api-football');
    const footballDataOrg = integrations.find((integration) => integration.provider === 'football-data-org');

    const saveMutation = useMutation({
        mutationFn: async (payload: { provider: string; data: Record<string, unknown> }) =>
            (await api.patch<ApiIntegration[]>(`/integrations/${payload.provider}`, payload.data)).data,
        onSuccess: () => {
            setMessage('Configuracao salva.');
            queryClient.invalidateQueries({ queryKey: ['integrations'] });
        },
        onError: (error) => setMessage(apiErrorMessage(error)),
    });

    const syncMutation = useMutation({
        mutationFn: async (payload: { provider: string; data: Record<string, unknown> }) =>
            (await api.post<ApiSyncResult>(`/integrations/${payload.provider}/sync`, payload.data)).data,
        onSuccess: (data) => {
            setSyncResult(data);
            setMessage('Atualizacao concluida.');
            queryClient.invalidateQueries({ queryKey: ['integrations'] });
            queryClient.invalidateQueries({ queryKey: ['matches'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
            queryClient.invalidateQueries({ queryKey: ['leagues'] });
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
        onError: (error) => setMessage(apiErrorMessage(error)),
    });

    function saveApiFootball(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const apiKey = String(form.get('api_key') ?? '').trim();
        const season = Number(form.get('season') || apiFootball?.config.season || 2024);
        const league = Number(form.get('league') || 71);
        const timezone = String(form.get('timezone') || 'America/Sao_Paulo');

        saveMutation.mutate({
            provider: 'api-football',
            data: {
                ...(apiKey ? { api_key: apiKey } : {}),
                is_active: true,
                config: { league, season, timezone },
            },
        });
    }

    function syncApiFootball() {
        const config = apiFootball?.config ?? { league: 71, season: 2024, timezone: 'America/Sao_Paulo' };
        syncMutation.mutate({
            provider: 'api-football',
            data: {
                league: config.league ?? 71,
                season: config.season ?? 2024,
                timezone: config.timezone ?? 'America/Sao_Paulo',
            },
        });
    }

    function saveFootballDataOrg(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const apiKey = String(form.get('api_key') ?? '').trim();
        const competition = String(form.get('competition') || footballDataOrg?.config.competition || 'BSA');
        const season = Number(form.get('season') || footballDataOrg?.config.season || 2024);

        saveMutation.mutate({
            provider: 'football-data-org',
            data: {
                ...(apiKey ? { api_key: apiKey } : {}),
                is_active: true,
                config: { competition, season },
            },
        });
    }

    function syncFootballDataOrg() {
        const config = footballDataOrg?.config ?? { competition: 'BSA', season: 2024 };
        syncMutation.mutate({
            provider: 'football-data-org',
            data: {
                competition: config.competition ?? 'BSA',
                season: config.season ?? 2024,
            },
        });
    }

    return (
        <>
            <PageTitle title="Integracoes" subtitle="Configure provedores de dados e atualize a base real do Brasileirao Serie A sem sair do sistema." />
            {isLoading ? (
                <Loading />
            ) : (
                <div className="space-y-6">
                    <div className="grid gap-6 xl:grid-cols-[420px_1fr]">
                        <div className="space-y-6">
                            <Panel>
                                <FormTitle icon={<KeyRound />} title="API-Football" />
                                <form onSubmit={saveApiFootball} className="space-y-4">
                                    <div>
                                        <p className="mb-2 text-xs uppercase text-slate-500">Chave atual</p>
                                        <p className="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-slate-300">
                                            {apiFootball?.configured ? apiFootball.key_preview ?? 'Configurada' : 'Nao configurada'}
                                        </p>
                                    </div>
                                    <Input name="api_key" placeholder="Nova API key, opcional se ja estiver configurada" autoComplete="off" />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input name="league" type="number" defaultValue={apiFootball?.config.league ?? 71} placeholder="ID da liga" />
                                        <Select
                                            name="season"
                                            defaultValue={String(apiFootball?.config.season ?? 2024)}
                                            options={[
                                                ['2024', 'Brasileirao 2024'],
                                                ['2023', 'Brasileirao 2023'],
                                                ['2022', 'Brasileirao 2022'],
                                            ]}
                                        />
                                    </div>
                                    <Input name="timezone" defaultValue={apiFootball?.config.timezone ?? 'America/Sao_Paulo'} />
                                    <IntegrationActions
                                        saving={saveMutation.isPending}
                                        syncing={syncMutation.isPending}
                                        canSync={!!apiFootball?.configured}
                                        syncLabel="Atualizar Serie A"
                                        onSync={syncApiFootball}
                                    />
                                </form>
                            </Panel>
                            <Panel>
                                <FormTitle icon={<KeyRound />} title="Football-Data.org" />
                                <form onSubmit={saveFootballDataOrg} className="space-y-4">
                                    <div>
                                        <p className="mb-2 text-xs uppercase text-slate-500">Chave atual</p>
                                        <p className="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-slate-300">
                                            {footballDataOrg?.configured ? footballDataOrg.key_preview ?? 'Configurada' : 'Nao configurada'}
                                        </p>
                                    </div>
                                    <Input name="api_key" placeholder="Token X-Auth-Token" autoComplete="off" />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input name="competition" defaultValue={footballDataOrg?.config.competition ?? 'BSA'} placeholder="Codigo da competicao" />
                                        <Select
                                            name="season"
                                            defaultValue={String(footballDataOrg?.config.season ?? 2024)}
                                            options={[
                                                ['2026', 'Brasileirao 2026'],
                                                ['2025', 'Brasileirao 2025'],
                                                ['2024', 'Brasileirao 2024'],
                                                ['2023', 'Brasileirao 2023'],
                                                ['2022', 'Brasileirao 2022'],
                                            ]}
                                        />
                                    </div>
                                    <IntegrationActions
                                        saving={saveMutation.isPending}
                                        syncing={syncMutation.isPending}
                                        canSync={!!footballDataOrg?.configured}
                                        syncLabel="Atualizar BSA"
                                        onSync={syncFootballDataOrg}
                                    />
                                </form>
                            </Panel>
                        </div>
                        <Panel>
                            <h2 className="mb-4 text-lg font-semibold text-white">Provedores</h2>
                            <div className="space-y-3">
                                {integrations.map((integration) => (
                                    <div key={integration.provider} className="rounded-lg border border-white/10 bg-slate-950/50 p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold text-white">{integration.name}</p>
                                                <p className="mt-1 max-w-2xl text-sm text-slate-400">{integration.description}</p>
                                            </div>
                                            <span className="rounded-lg border border-white/10 px-3 py-1 text-xs text-slate-300">
                                                {integrationStatusLabel(integration.status)}
                                            </span>
                                        </div>
                                        <div className="mt-4 grid gap-3 sm:grid-cols-4">
                                            <Badge label="Chave" value={integration.configured ? 'Configurada' : 'Pendente'} />
                                            <Badge label="Ativo" value={integration.is_active ? 'Sim' : 'Nao'} />
                                            <Badge label="Ultima atualizacao" value={integration.last_synced_at ? formatDate(integration.last_synced_at) : '-'} />
                                            <Badge
                                                label="Ultimo resumo"
                                                value={
                                                    integration.last_sync_summary
                                                        ? `${integration.last_sync_summary.created ?? 0} novas / ${integration.last_sync_summary.updated ?? 0} atualizadas`
                                                        : '-'
                                                }
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Panel>
                    </div>
                    {(message || saveMutation.isPending || syncMutation.isPending) && (
                        <Panel>
                            <p className="text-sm text-slate-300">
                                {saveMutation.isPending || syncMutation.isPending ? 'Processando integracao...' : message}
                            </p>
                        </Panel>
                    )}
                    {syncResult && (
                        <div className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <Metric icon={<CalendarDays />} label="Fixtures" value={syncResult.summary.total_rows} />
                                <Metric icon={<ListPlus />} label="Criadas" value={syncResult.summary.created} />
                                <Metric icon={<RefreshCw />} label="Atualizadas" value={syncResult.summary.updated} />
                                <Metric icon={<Shield />} label="Puladas" value={syncResult.summary.skipped} />
                            </div>
                            <Panel>
                                <h2 className="mb-4 text-lg font-semibold text-white">Ultima sincronizacao</h2>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[760px] text-left text-sm">
                                        <thead className="text-xs uppercase text-slate-500">
                                            <tr>
                                                <th className="border-b border-white/10 pb-3 font-medium">Acao</th>
                                                <th className="border-b border-white/10 pb-3 font-medium">Liga</th>
                                                <th className="border-b border-white/10 pb-3 font-medium">Jogo</th>
                                                <th className="border-b border-white/10 pb-3 font-medium">Data</th>
                                                <th className="border-b border-white/10 pb-3 font-medium">Placar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {syncResult.rows.slice(0, 30).map((row, index) => (
                                                <tr key={`${row.match_id}-${index}`} className="border-b border-white/5 last:border-0">
                                                    <td className="py-3 text-slate-300">{row.action}</td>
                                                    <td className="py-3 text-slate-300">{row.data.league ?? '-'}</td>
                                                    <td className="py-3 text-white">{row.data.home_team ?? '-'} x {row.data.away_team ?? '-'}</td>
                                                    <td className="py-3 text-slate-300">{row.data.date ? formatDate(row.data.date) : '-'}</td>
                                                    <td className="py-3 text-slate-300">{row.data.home_goals ?? '-'} - {row.data.away_goals ?? '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Panel>
                        </div>
                    )}
                </div>
            )}
        </>
    );
}

function BankrollPage() {
    const queryClient = useQueryClient();
    const [filters, setFilters] = useState({ bankroll_id: '', status: '' });
    const [selectedMarket, setSelectedMarket] = useState('Mais de 1.5 gols');
    const [selectedBetMatchId, setSelectedBetMatchId] = useState('');
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '')).toString();
    const { data: bankrolls = [] } = useApiQuery<Bankroll[]>(['bankrolls'], '/bankrolls');
    const { data: bookmakers = [] } = useApiQuery<Bookmaker[]>(['bookmakers'], '/bookmakers');
    const { data: matches = [] } = useApiQuery<Match[]>(['matches-for-bets'], '/matches?sort=starts_at&bettable=1');
    const { data: bets = [] } = useApiQuery<Bet[]>(['bets', filters], `/bets${query ? `?${query}` : ''}`);
    const selectedBetMatch = matches.find((match) => String(match.id) === selectedBetMatchId);

    const bankrollMutation = useMutation({
        mutationFn: (payload: Record<string, FormDataEntryValue>) => api.post('/bankrolls', payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['bankrolls'] }),
    });

    const betMutation = useMutation({
        mutationFn: (payload: Record<string, FormDataEntryValue>) => api.post('/bets', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['bets'] });
            queryClient.invalidateQueries({ queryKey: ['bankrolls'] });
        },
    });

    const statusMutation = useMutation({
        mutationFn: ({ id, status }: { id: number; status: Bet['status'] }) =>
            api.patch(`/bets/${id}`, { status, settled_at: status === 'pending' ? null : new Date().toISOString() }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['bets'] });
            queryClient.invalidateQueries({ queryKey: ['bankrolls'] });
        },
    });

    function updateFilter(event: React.ChangeEvent<HTMLSelectElement>) {
        setFilters((current) => ({ ...current, [event.target.name]: event.target.value }));
    }

    return (
        <>
            <PageTitle title="Banca e Apostas" subtitle="Registre bancas, casas, stakes, odds e resultados para acompanhar desempenho e alimentar analises futuras." />
            <div className="grid gap-6 xl:grid-cols-[360px_1fr]">
                <div className="space-y-6">
                    <Panel>
                        <FormTitle icon={<WalletCards />} title="Nova banca" />
                        <form onSubmit={(event) => submitForm(event, bankrollMutation.mutate)} className="space-y-3">
                            <Select name="bookmaker_id" required placeholder="Casa da banca" options={bookmakers.map((bookmaker) => [String(bookmaker.id), bookmaker.name])} />
                            <Input name="name" placeholder="Nome da banca" required />
                            <Input name="initial_balance" type="number" step="0.01" min="0" placeholder="Valor inicial" required />
                            <Input name="currency" defaultValue="BRL" maxLength={3} />
                            <Input name="notes" placeholder="Observacoes" />
                            <Button>Criar banca</Button>
                        </form>
                    </Panel>
                    <Panel>
                        <FormTitle icon={<ReceiptText />} title="Nova aposta" />
                        <form onSubmit={(event) => submitForm(event, betMutation.mutate)} className="space-y-3">
                            <Select name="bankroll_id" required placeholder="Selecione a banca" options={bankrolls.map((bankroll) => [String(bankroll.id), bankroll.name])} />
                            <Select
                                name="match_id"
                                value={selectedBetMatchId}
                                onChange={(event) => setSelectedBetMatchId(event.target.value)}
                                placeholder="Partida opcional"
                                options={matches.slice(0, 500).map((match) => [
                                    String(match.id),
                                    `${formatDate(match.starts_at)} - ${match.home_team?.name} x ${match.away_team?.name}`,
                                ])}
                            />
                            <Input name="placed_at" type="datetime-local" defaultValue={toDatetimeLocal(new Date())} required />
                            <Select
                                name="market"
                                value={selectedMarket}
                                onChange={(event) => setSelectedMarket(event.target.value)}
                                required
                                options={betMarkets}
                            />
                            <Select
                                name="selection"
                                key={`${selectedMarket}-${selectedBetMatchId}`}
                                required
                                options={betSelectionOptions(selectedMarket, selectedBetMatch)}
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Input name="stake" type="number" step="0.01" min="0.01" placeholder="Stake" required />
                                <Input name="odds" type="number" step="0.01" min="1.01" placeholder="Odd" required />
                            </div>
                            <Select name="status" required options={[
                                ['pending', 'Pendente'],
                                ['won', 'Ganha'],
                                ['lost', 'Perdida'],
                                ['void', 'Anulada'],
                                ['cashed_out', 'Cash out'],
                            ]} />
                            <Input name="notes" placeholder="Observacoes" />
                            <Button>Registrar aposta</Button>
                        </form>
                    </Panel>
                </div>
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {bankrolls.map((bankroll) => (
                            <Panel key={bankroll.id}>
                                <p className="text-sm text-slate-400">{bankroll.name}</p>
                                <p className="mt-1 text-xs text-slate-500">{bankroll.bookmaker?.name ?? 'Casa nao definida'}</p>
                                <p className="mt-2 text-2xl font-semibold text-white">{money(bankroll.current_balance, bankroll.currency)}</p>
                                <p className={`mt-1 text-sm ${bankroll.profit >= 0 ? 'text-emerald-200' : 'text-rose-300'}`}>
                                    {money(bankroll.profit, bankroll.currency)} · ROI {bankroll.roi}%
                                </p>
                                <p className="mt-2 text-xs text-slate-500">{bankroll.bets_count} apostas · {bankroll.settled_bets_count} encerradas</p>
                            </Panel>
                        ))}
                        {bankrolls.length === 0 && <Panel><p className="text-sm text-slate-400">Crie uma banca para registrar apostas.</p></Panel>}
                    </div>
                    <Panel>
                        <div className="mb-4 grid gap-3 md:grid-cols-3">
                            <Select name="bankroll_id" value={filters.bankroll_id} onChange={updateFilter} placeholder="Todas as bancas" options={bankrolls.map((bankroll) => [String(bankroll.id), bankroll.name])} />
                            <Select name="status" value={filters.status} onChange={updateFilter} placeholder="Todos os status" options={[
                                ['pending', 'Pendente'],
                                ['won', 'Ganha'],
                                ['lost', 'Perdida'],
                                ['void', 'Anulada'],
                                ['cashed_out', 'Cash out'],
                            ]} />
                            <button type="button" onClick={() => setFilters({ bankroll_id: '', status: '' })} className="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/5">
                                Limpar filtros
                            </button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[980px] text-left text-sm">
                                <thead className="text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="border-b border-white/10 pb-3 font-medium">Data</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Banca</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Casa</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Partida</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Aposta</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Stake/Odd</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Status</th>
                                        <th className="border-b border-white/10 pb-3 font-medium">Lucro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bets.map((bet) => (
                                        <tr key={bet.id} className="border-b border-white/5 last:border-0">
                                            <td className="py-3 text-slate-300">{formatDate(bet.placed_at)}</td>
                                            <td className="py-3 text-slate-300">{bet.bankroll?.name ?? '-'}</td>
                                            <td className="py-3 text-slate-300">{bet.bookmaker?.name ?? '-'}</td>
                                            <td className="py-3 text-white">{bet.match?.label ?? '-'}</td>
                                            <td className="py-3 text-slate-300">{bet.market}<br /><span className="text-xs text-emerald-200">{bet.selection}</span></td>
                                            <td className="py-3 text-slate-300">{money(bet.stake, bet.bankroll?.currency)}<br /><span className="text-xs">odd {bet.odds}</span></td>
                                            <td className="py-3">
                                                <Select
                                                    value={bet.status}
                                                    onChange={(event) => statusMutation.mutate({ id: bet.id, status: event.target.value as Bet['status'] })}
                                                    options={[
                                                        ['pending', 'Pendente'],
                                                        ['won', 'Ganha'],
                                                        ['lost', 'Perdida'],
                                                        ['void', 'Anulada'],
                                                        ['cashed_out', 'Cash out'],
                                                    ]}
                                                />
                                            </td>
                                            <td className={`py-3 font-semibold ${bet.profit >= 0 ? 'text-emerald-200' : 'text-rose-300'}`}>{money(bet.profit, bet.bankroll?.currency)}</td>
                                        </tr>
                                    ))}
                                    {bets.length === 0 && (
                                        <tr>
                                            <td colSpan={8} className="py-6 text-center text-slate-400">Nenhuma aposta registrada.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                </div>
            </div>
        </>
    );
}

function IntegrationActions({
    saving,
    syncing,
    canSync,
    syncLabel,
    onSync,
}: {
    saving: boolean;
    syncing: boolean;
    canSync: boolean;
    syncLabel: string;
    onSync: () => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <button
                type="submit"
                disabled={saving}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <Settings size={16} />
                Salvar
            </button>
            <button
                type="button"
                onClick={onSync}
                disabled={syncing || !canSync}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-300 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-sky-200 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <RefreshCw size={16} />
                {syncLabel}
            </button>
        </div>
    );
}

function MatchList({ title, matches, showLinks = false }: { title: string; matches: Match[]; showLinks?: boolean }) {
    return (
        <Panel>
            <h2 className="mb-4 text-lg font-semibold text-white">{title}</h2>
            <div className="space-y-3">
                {matches.length === 0 && <p className="text-sm text-slate-400">Nenhum registro encontrado.</p>}
                {matches.map((match) => (
                    <div key={match.id} className="rounded-lg border border-white/10 bg-slate-950/50 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-medium text-white">
                                    {match.home_team?.name} x {match.away_team?.name}
                                </p>
                                <p className="mt-1 text-sm text-slate-400">
                                    {match.league?.name} · {formatDate(match.starts_at)}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="font-semibold text-emerald-200">{score(match)}</p>
                                <p className="text-xs uppercase text-slate-500">{match.status}</p>
                            </div>
                        </div>
                        {showLinks && (
                            <Link to={`/matches/${match.id}`} className="mt-3 inline-flex items-center gap-1 text-sm text-emerald-300 hover:text-emerald-200">
                                Abrir detalhes <ChevronRight size={16} />
                            </Link>
                        )}
                    </div>
                ))}
            </div>
        </Panel>
    );
}

function AnalysisResultView({ analysis }: { analysis: Analysis }) {
    return (
        <div className="mt-5 space-y-4">
            <div>
                <p className="text-sm text-slate-400">Mercado sugerido</p>
                <p className="text-xl font-semibold text-white">{suggestedMarketLabel(analysis.suggested_market)}</p>
                <p className="text-sm text-emerald-200">{suggestedSelectionLabel(analysis.suggested_market, analysis.suggested_selection)}</p>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <Badge label="Confianca" value={`${analysis.confidence}%`} />
                <Badge label="Risco" value={riskLabel(analysis.risk_level)} />
            </div>
            <Badge label="Resultado" value={statusLabel(analysis.result_status)} />
            {analysis.evaluation_reason && <p className="text-sm text-slate-400">{analysis.evaluation_reason}</p>}
            <p className="text-sm text-slate-300">{analysis.summary}</p>
            <ul className="space-y-2 text-sm text-slate-400">
                {analysis.factors.map((factor) => (
                    <li key={factor} className="flex gap-2">
                        <ChevronRight className="mt-0.5 shrink-0 text-emerald-300" size={16} />
                        <span>{factor}</span>
                    </li>
                ))}
            </ul>
            {analysis.generated_at && <p className="text-xs text-slate-500">Gerado em {formatDate(analysis.generated_at)}</p>}
        </div>
    );
}

function TeamMetricsCard({ title, form, side }: { title: string; form: TeamForm; side: 'home' | 'away' }) {
    const venue = side === 'home' ? form.home : form.away;

    return (
        <Panel>
            <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-white">{title}</h2>
                    <p className="text-sm text-slate-400">{form.sample_size} partidas finalizadas na amostra</p>
                </div>
                <Badge label={side === 'home' ? 'Casa' : 'Fora'} value={`${venue.games} jogos`} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <SmallMetric label="Ultimos 10" value={`${form.last_10.wins}V ${form.last_10.draws}E ${form.last_10.losses}D`} />
                <SmallMetric label="Gols pro/contra" value={`${form.last_10.goals_for}/${form.last_10.goals_against}`} />
                <SmallMetric label="Media gols marcados" value={String(form.last_10.avg_goals_for)} />
                <SmallMetric label="Media gols sofridos" value={String(form.last_10.avg_goals_against)} />
                <SmallMetric label="Over 1.5" value={`${form.last_10.over_1_5_rate}%`} />
                <SmallMetric label="Over 2.5" value={`${form.last_10.over_2_5_rate}%`} />
                <SmallMetric label="Ambas marcam" value={`${form.last_10.both_teams_score_rate}%`} />
                <SmallMetric label="Marcou 1+" value={`${form.last_10.scored_at_least_one_rate}%`} />
                <SmallMetric label="Sofreu 1+" value={`${form.last_10.conceded_at_least_one_rate}%`} />
                <SmallMetric label="Nao derrota" value={`${venue.non_loss_rate}%`} />
            </div>
        </Panel>
    );
}

function SuggestionsCard({ suggestions }: { suggestions: Suggestion[] }) {
    return (
        <Panel className="xl:col-span-2">
            <h2 className="mb-4 text-lg font-semibold text-white">Sugestoes consideradas</h2>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="text-xs uppercase text-slate-500">
                        <tr>
                            <th className="border-b border-white/10 pb-3 font-medium">Mercado</th>
                            <th className="border-b border-white/10 pb-3 font-medium">Selecao</th>
                            <th className="border-b border-white/10 pb-3 font-medium">Score</th>
                            <th className="border-b border-white/10 pb-3 font-medium">Confianca</th>
                            <th className="border-b border-white/10 pb-3 font-medium">Risco</th>
                            <th className="border-b border-white/10 pb-3 font-medium">Principal fator</th>
                        </tr>
                    </thead>
                    <tbody>
                        {suggestions.map((suggestion) => (
                            <tr key={suggestion.market} className="border-b border-white/5 last:border-0">
                                <td className="py-3 text-white">{suggestedMarketLabel(suggestion.market)}</td>
                                <td className="py-3 text-slate-300">{suggestedSelectionLabel(suggestion.market, suggestion.selection)}</td>
                                <td className="py-3 text-slate-300">{suggestion.score}</td>
                                <td className="py-3 text-emerald-200">{suggestion.confidence}%</td>
                                <td className="py-3 text-slate-300">{riskLabel(suggestion.risk_level)}</td>
                                <td className="max-w-sm py-3 text-slate-400">{suggestion.reasons[0] ?? '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Panel>
    );
}

function SmallMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-white/10 bg-slate-950/50 p-3">
            <p className="text-xs text-slate-500">{label}</p>
            <p className="mt-1 font-semibold text-white">{value}</p>
        </div>
    );
}

function TeamBlock({ team, alignRight = false }: { team?: Team; alignRight?: boolean }) {
    return (
        <div className={alignRight ? 'text-right' : ''}>
            <p className="text-2xl font-semibold text-white">{team?.name ?? '-'}</p>
            <p className="mt-1 text-sm text-slate-400">{team?.short_name ?? 'Time'}</p>
        </div>
    );
}

function DataTable({ headers, rows }: { headers: string[]; rows: string[][] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-left text-sm">
                <thead className="text-xs uppercase text-slate-500">
                    <tr>{headers.map((header) => <th key={header} className="border-b border-white/10 pb-3 font-medium">{header}</th>)}</tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index} className="border-b border-white/5 last:border-0">
                            {row.map((cell, cellIndex) => <td key={cellIndex} className="py-3 text-slate-300">{cell}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function FormTitle({ icon, title }: { icon: React.ReactNode; title: string }) {
    return <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-white">{icon}{title}</h2>;
}

function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm outline-none focus:border-emerald-300" />;
}

function Select({ options, placeholder, ...props }: React.SelectHTMLAttributes<HTMLSelectElement> & { options: [string, string][]; placeholder?: string }) {
    return (
        <select {...props} className="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm outline-none focus:border-emerald-300">
            {placeholder && <option value="">{placeholder}</option>}
            {options.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
    );
}

function Button({ children }: { children: React.ReactNode }) {
    return <button className="w-full rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-300">{children}</button>;
}

function Badge({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-white/10 bg-slate-950/60 p-3">
            <p className="text-xs text-slate-500">{label}</p>
            <p className="mt-1 font-semibold text-white">{value}</p>
        </div>
    );
}

function Loading() {
    return <Panel><p className="text-sm text-slate-400">Carregando dados locais...</p></Panel>;
}

function submitForm(event: FormEvent<HTMLFormElement>, submit: (payload: Record<string, FormDataEntryValue>) => void) {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());
    submit(payload);
    form.reset();
}

function formatDate(date?: string) {
    if (!date) return '-';
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(date));
}

function toDatetimeLocal(date: Date) {
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function money(value?: number | null, currency = 'BRL') {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: currency ?? 'BRL' }).format(value ?? 0);
}

function score(match?: Match) {
    if (!match || match.status !== 'finished') return 'vs';
    return `${match.home_goals ?? 0} - ${match.away_goals ?? 0}`;
}

function statusLabel(status?: string | null) {
    return {
        pending: 'Pendente',
        won: 'Acertou',
        lost: 'Errou',
        void: 'Anulada',
        unknown: 'Desconhecido',
    }[status ?? 'pending'] ?? 'Pendente';
}

function riskLabel(risk?: string | null) {
    return {
        low: 'Baixo',
        medium: 'Medio',
        high: 'Alto',
    }[risk ?? ''] ?? '-';
}

function shortText(text?: string | null) {
    if (!text) return '-';
    return text.length > 110 ? `${text.slice(0, 110)}...` : text;
}

function importStatusLabel(status: string) {
    return {
        valid: 'Valida',
        duplicate: 'Duplicada',
        invalid: 'Invalida',
        skipped: 'Pulada',
    }[status] ?? status;
}

function integrationStatusLabel(status: string) {
    return {
        ready: 'Pronto',
        planned: 'Planejado',
        available: 'Disponivel',
    }[status] ?? status;
}

function apiErrorMessage(error: unknown) {
    if (axios.isAxiosError(error)) {
        const message = error.response?.data?.message;

        return typeof message === 'string' ? message : 'A operacao falhou. Verifique os dados e tente novamente.';
    }

    return 'A operacao falhou. Verifique os dados e tente novamente.';
}

function toPerformanceGroup(row: BacktestSummary & { label: string }): PerformanceGroup {
    return {
        label: row.label,
        total: row.total_matches,
        won: row.won,
        lost: row.lost,
        win_rate: row.win_rate,
    };
}

createRoot(document.getElementById('root')!).render(<App />);
