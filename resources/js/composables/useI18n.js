import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const messages = {
    en: {
        dashboard: 'Dashboard',
        rounds: 'Rounds',
        teams: 'Teams',
        tournaments: 'Tournaments',
        control: 'Control',
        display: 'Display',
        timetable: 'Timetable',
        userApprovals: 'User Approvals',
        logout: 'Log Out',
        profile: 'Profile',
        startCompetition: 'Start Competition',
        endCompetition: 'End Competition',
        toBuzzerRound: 'To Buzzer Round',
        toFeverRound: 'To Fever Round',
        toUltimateFeverRound: 'To Ultimate Fever Round',
        undo: 'Undo',
        clear: 'Clear',
        roundStatus: 'Round Status',
        roundPhase: 'Current Phase',
        roundLabel: 'Round',
        scores: 'Scores',
        teamNames: 'Team Names',
        lightningRound: 'Lightning Round',
        buzzerRound: 'Buzzer Round',
        buzzerNormalRound: 'Buzzer Round (Normal)',
        buzzerFeverRound: 'Buzzer Round (Fever)',
        buzzerUltimateRound: 'Buzzer Round (Ultimate Fever)',
        confirmToBuzzerRound: 'Move to Buzzer Round (Normal)?',
        confirmToFeverRound: 'Move to Fever Round?',
        confirmToUltimateFeverRound: 'Move to Ultimate Fever Round?',
    },
    zh: {
        dashboard: '控制台',
        rounds: '回合',
        teams: '隊伍',
        tournaments: '賽事',
        control: '控制頁',
        display: '展示頁',
        timetable: '時間表',
        userApprovals: '使用者審批',
        logout: '登出',
        profile: '個人資料',
        startCompetition: '開始比賽',
        endCompetition: '結束比賽',
        toBuzzerRound: '轉到搶答環節',
        toFeverRound: '轉到 Fever 環節',
        toUltimateFeverRound: '轉到 Ultimate Fever 環節',
        undo: '撤銷',
        clear: '清空',
        roundStatus: '回合狀態',
        roundPhase: '目前環節',
        roundLabel: '回合',
        scores: '分數',
        teamNames: '隊名',
        lightningRound: '必答環節',
        buzzerRound: '搶答環節',
        buzzerNormalRound: '搶答環節（普通）',
        buzzerFeverRound: '搶答環節（Fever）',
        buzzerUltimateRound: '搶答環節（Ultimate Fever）',
        confirmToBuzzerRound: '確認轉到搶答環節（普通）？',
        confirmToFeverRound: '確認轉到 Fever 環節？',
        confirmToUltimateFeverRound: '確認轉到 Ultimate Fever 環節？',
    },
};

export function useI18n() {
    const page = usePage();
    const locale = computed(() => page.props.locale || 'en');

    const t = (key) => messages[locale.value]?.[key] || messages.en[key] || key;

    const switchLocale = (nextLocale) => {
        router.post(route('locale.update'), { locale: nextLocale }, { preserveScroll: true, preserveState: true });
    };

    return { locale, t, switchLocale };
}
