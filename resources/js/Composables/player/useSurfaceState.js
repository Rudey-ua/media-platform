import { computed, ref } from 'vue';
import {
    PLAYER_IDLE_DESCRIPTION,
    PLAYER_IDLE_TITLE,
} from './playerConstants';

export function useSurfaceState() {
    const surfaceMode = ref('idle');
    const surfaceVariant = ref(null);
    const surfaceTitle = ref(PLAYER_IDLE_TITLE);
    const surfaceDescription = ref(PLAYER_IDLE_DESCRIPTION);

    function setPlayerSurfaceMode(mode, payload = {}) {
        surfaceMode.value = mode;
        surfaceVariant.value = payload.variant ?? null;

        if (mode === 'loading') {
            surfaceTitle.value = payload.title ?? 'Preparing playback...';
            surfaceDescription.value = payload.description ?? 'Fetching playlist and initializing video player.';
            return;
        }

        if (mode === 'idle') {
            surfaceTitle.value = PLAYER_IDLE_TITLE;
            surfaceDescription.value = PLAYER_IDLE_DESCRIPTION;
            return;
        }

        surfaceTitle.value = payload.title ?? PLAYER_IDLE_TITLE;
        surfaceDescription.value = payload.description ?? PLAYER_IDLE_DESCRIPTION;
    }

    const surfaceBorderClass = computed(() => {
        if (surfaceMode.value === 'message' && surfaceVariant.value === 'error') {
            return 'border-red-300';
        }

        return 'border-slate-200';
    });

    return {
        surfaceMode,
        surfaceTitle,
        surfaceDescription,
        surfaceBorderClass,
        setPlayerSurfaceMode,
    };
}
