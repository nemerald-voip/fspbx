<template>
    <audio ref="audio" :src="url" preload="metadata" class="hidden" />
    <!-- Mobile layout -->
    <div class="block md:hidden">
        <div class="w-full max-w-xl mx-auto rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <!-- Top bar: timeline (full width) -->
            <div class="px-4 pt-3">
                <input type="range" class="slider text-indigo-500 accent-indigo-500 w-full" min="0" max="100" step="0.1"
                    :value="progress" @input="onTimeSlider" @change="onTimeSlider" />
            </div>

            <!-- Bottom controls: center row -->
            <div class="px-4 pb-4 pt-3">
                <div class="flex items-center justify-center gap-6">
                    <!-- Volume -->
                    <button @click="toggleMute"
                        class="grid h-9 w-9 place-items-center text-slate-600 hover:text-slate-800">
                        <svg v-if="muted || volume === 0" class="h-5 w-5" viewBox="0 0 24 24">
                            <use href="#off" fill="currentColor" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24">
                            <use href="#high" fill="currentColor" />
                        </svg>
                    </button>

                    <!-- Back 10s -->
                    <button @click="seekBy(-10)"
                        class="grid h-10 w-10 place-items-center text-slate-500 hover:text-slate-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none">
                            <use href="#backward" stroke="currentColor" />
                        </svg>
                    </button>

                    <!-- Play / Pause (big circle) -->
                    <button @click="toggle"
                        class="grid h-14 w-14 place-items-center rounded-full bg-slate-800 text-white hover:bg-slate-700 transition"
                        :title="playing ? 'Pause' : 'Play'">
                        <svg v-if="!playing" class="h-6 w-6" viewBox="0 0 24 24">
                            <use href="#play" fill="currentColor" />
                        </svg>
                        <svg v-else class="h-6 w-6" viewBox="0 0 24 24">
                            <use href="#pause" fill="currentColor" />
                        </svg>
                    </button>

                    <!-- Forward 10s -->
                    <button @click="seekBy(10)"
                        class="grid h-10 w-10 place-items-center text-slate-500 hover:text-slate-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none">
                            <use href="#forward" stroke="currentColor" />
                        </svg>
                    </button>

                    <!-- Speed -->
                    <button @click="cycleRate" class="text-sm font-semibold text-slate-600 hover:text-slate-800"> {{
                        rate }}x </button>

                    <!-- Download (mobile) -->
                    <button @click="handleDownload" :disabled="isDownloading"
                        class="grid h-9 w-9 place-items-center rounded-full text-slate-600 hover:text-slate-800 disabled:opacity-50"
                        title="Download">
                        <DownloadIcon v-if="!isDownloading" class="h-6 w-6 text-slate-600" aria-hidden="true" />
                        <Spinner :show="isDownloading"
                            class="h-6 w-6 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 active:bg-gray-300 active:duration-150 cursor-pointer" />
                    </button>

                </div>
            </div>
        </div>

    </div>

    <!-- Desktop layout -->
    <div class="hidden md:block">
        <div class="w-full rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 px-4 py-3">

            <div class="flex items-center gap-4">
                <!-- Rewind 10s -->
                <button type="button" class="grid h-10 w-10 place-items-center text-slate-500 hover:text-slate-700"
                    @click="seekBy(-10)" title="Back 10s">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none">
                        <use href="#backward" stroke="currentColor" />
                    </svg>
                </button>

                <!-- Play / Pause -->
                <button type="button"
                    class="grid h-10 w-12 place-items-center rounded-full bg-slate-800 text-white hover:bg-slate-700 transition"
                    @click="toggle" :title="playing ? 'Pause' : 'Play'">
                    <!-- Play -->
                    <svg v-if="!playing" class="h-6 w-6" viewBox="0 0 24 24" aria-hidden="true">
                        <!-- fill uses currentColor so the icon is white on the dark button -->
                        <use href="#play" fill="currentColor" />
                    </svg>

                    <!-- Pause -->
                    <svg v-else class="h-6 w-6" viewBox="0 0 24 24" aria-hidden="true">
                        <use href="#pause" fill="currentColor" />
                    </svg>
                </button>

                <!-- Forward 10s -->
                <button type="button" class="grid h-10 w-10 place-items-center text-slate-500 hover:text-slate-700"
                    @click="seekBy(10)" title="Forward 10s">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <!-- color comes from current text color -->
                        <use href="#forward" stroke="currentColor" />
                    </svg>
                </button>

                <!-- Center area: time + progress + duration -->
                <div class="flex items-center gap-3 w-full">
                    <!-- current time -->
                    <div class="w-12 text-right text-sm tabular-nums text-slate-600">
                        {{ fmt(current) }}
                    </div>

                    <!-- progress bar -->
                    <input type="range" class="slider text-indigo-500 accent-indigo-500 w-full" min="0" max="100"
                        step="0.1" :value="progress" @input="onTimeSlider" @change="onTimeSlider" />

                    <!-- duration -->
                    <div class="w-12 text-sm tabular-nums text-slate-600">
                        {{ fmt(duration) }}
                    </div>

                    <!-- 1x speed -->
                    <button type="button" class="text-sm text-slate-600 hover:text-slate-800 select-none"
                        @click="cycleRate" title="Playback speed">
                        {{ rate }}x
                    </button>

                    <!-- Volume button -->
                    <button type="button" class="grid h-9 w-9 place-items-center text-slate-600 hover:text-slate-800"
                        @click="toggleMute" :title="muted || volume === 0 ? 'Unmute' : 'Mute'">
                        <!-- muted/off -->
                        <svg v-if="muted || volume === 0" class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                            <use href="#off" fill="currentColor" />
                        </svg>
                        <!-- high -->
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                            <use href="#high" fill="currentColor" />
                        </svg>
                    </button>

                    <!-- Volume slider -->
                    <input type="range" min="0" max="1" step="0.01" v-model.number="volume"
                        class="w-14 sm:w-20 lg:w-28 accent-indigo-500" :title="`Volume: ${(volume * 100) | 0}%`" />

                    <!-- Download (desktop) -->
                    <button @click="handleDownload" :disabled="isDownloading"
                        class="ml-2 grid h-9 w-9 place-items-center rounded-full text-slate-600 hover:text-slate-800 disabled:opacity-50"
                        title="Download">
                        <DownloadIcon v-if="!isDownloading" class="h-6 w-6" aria-hidden="true" />
                        <Spinner :show="isDownloading"
                            class="h-6 w-6 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 active:bg-gray-300 active:duration-150 cursor-pointer" />
                    </button>


                </div>
            </div>
        </div>
    </div>



    <svg xmlns="http://www.w3.org/2000/svg" class="hidden">
        <symbol id="backward" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 5L5 8M5 8L8 11M5 8H13.5C16.5376 8 19 10.4624 19 13.5C19 15.4826 18.148 17.2202 17 18.188">
            </path>
            <path d="M5 15V19"></path>
            <path
                d="M8 18V16C8 15.4477 8.44772 15 9 15H10C10.5523 15 11 15.4477 11 16V18C11 18.5523 10.5523 19 10 19H9C8.44772 19 8 18.5523 8 18Z">
            </path>
        </symbol>

        <symbol id="play" viewBox="0 0 24 24">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.5 5.653c0-1.426 1.529-2.33 2.779-1.643l11.54 6.348c1.295.712 1.295 2.573 0
         3.285L7.28 19.991c-1.25.687-2.779-.217-2.779-1.643V5.653z" />
        </symbol>

        <symbol id="pause" viewBox="0 0 24 24">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75-.75H9a.75.75 0 01.75.75v13.5a.75.75 0
         01-.75.75H7.5a.75.75 0 01-.75-.75V5.25zm7.5 0A.75.75 0 0115 4.5h1.5a.75.75 0 01.75.75v13.5a.75.75 0
         01-.75.75H15a.75.75 0 01-.75-.75V5.25z" />
        </symbol>

        <symbol id="forward" viewBox="0 0 24 24">
            <path d="M16 5L19 8M19 8L16 11M19 8H10.5C7.46243 8 5 10.4624 5 13.5C5 15.4826 5.85204 17.2202 7 18.188"
                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M13 15V19" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M16 18V16C16 15.4477 16.4477 15 17 15H18C18.5523 15 19 15.4477 19 16V18C19 18.5523 18.5523 19 18
      19H17C16.4477 19 16 18.5523 16 18Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        </symbol>


        <symbol id="high" viewBox="0 0 24 24">
            <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0
      001.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276
      2.561-1.06V4.06zM18.584 5.106a.75.75 0 011.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 11-1.06-1.06
      8.25 8.25 0 000-11.668.75.75 0 010-1.06z"></path>
            <path d="M15.932 7.757a.75.75 0 011.061 0 6 6 0 010 8.486.75.75 0 01-1.06-1.061 4.5 4.5 0 000-6.364.75.75 0
      010-1.06z"></path>
        </symbol>

        <symbol id="off" viewBox="0 0 24 24">
            <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0
      001.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276
      2.561-1.06V4.06zM17.78 9.22a.75.75 0 10-1.06 1.06L18.44 12l-1.72 1.72a.75.75 0 001.06 1.06l1.72-1.72 1.72
      1.72a.75.75 0 101.06-1.06L20.56 12l1.72-1.72a.75.75 0 00-1.06-1.06l-1.72 1.72-1.72-1.72z" />
        </symbol>

    </svg>


</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import DownloadIcon from "@icons/DownloadIcon.vue"
import Spinner from "@generalComponents/Spinner.vue";

const props = defineProps({
    url: { type: String, required: true },
    downloadUrl: { type: String, default: null },
    fileName: { type: String, default: 'recording.wav' },
})

const audio = ref(null)
const bar = ref(null)
const playing = ref(false)
const current = ref(0)
const duration = ref(0)
const progress = ref(0)
const rate = ref(1)

// volume / mute state
const volume = ref(1)   // 0..1
const muted = ref(false)

let raf

const fmt = (s) => {
    s = Math.floor(s || 0)
    const m = String(Math.floor(s / 60)).padStart(2, '0')
    const r = String(s % 60).padStart(2, '0')
    return `${m}:${r}`
}

const update = () => {
    const a = audio.value
    if (!a) return
    current.value = a.currentTime || 0
    duration.value = a.duration || 0
    progress.value = duration.value ? (current.value / duration.value) * 100 : 0
    raf = requestAnimationFrame(update)
}

const toggle = async () => {
    const a = audio.value
    if (!a) return
    try {
        if (a.paused) { await a.play(); playing.value = true }
        else { a.pause(); playing.value = false }
    } catch (e) {
        console.warn('Playback failed:', e)
    }
}

const seekBy = (sec) => {
    const a = audio.value
    if (!a) return
    a.currentTime = Math.max(0, Math.min((a.currentTime || 0) + sec, a.duration || Infinity))
}

const seekTo = (e) => {
    const a = audio.value
    if (!a || !duration.value || !bar.value) return
    const rect = bar.value.getBoundingClientRect()
    const ratio = Math.min(Math.max((e.clientX - rect.left) / rect.width, 0), 1)
    a.currentTime = ratio * duration.value
}

const onTimeSlider = (e) => {
    const a = audio.value
    if (!a || !duration.value) return
    const val = Number(e.target.value) // 0..100
    a.currentTime = (val / 100) * duration.value
}

const startSeek = () => {
    const move = (e) => seekTo(e)
    const up = () => {
        window.removeEventListener('mousemove', move)
        window.removeEventListener('mouseup', up)
    }
    window.addEventListener('mousemove', move)
    window.addEventListener('mouseup', up)
}

const cycleRate = () => {
    // 0.5x → 1x → 1.5x → 2x → back
    const steps = [0.5, 1, 1.5, 2]
    const i = steps.indexOf(rate.value)
    rate.value = steps[(i + 1) % steps.length]
    if (audio.value) audio.value.playbackRate = rate.value
}

/** Volume / mute helpers */
const applyVolume = () => {
    const a = audio.value
    if (!a) return
    // keep slider position when muted; audio respects both props
    a.muted = muted.value
    a.volume = volume.value
}

const toggleMute = () => {
    muted.value = !muted.value
    applyVolume()
}

const isDownloading = ref(false)

const handleDownload = () => {
  const href = props.downloadUrl;  
  if (!href) return;
  const a = document.createElement('a');
  a.href = href;
  document.body.appendChild(a);
  a.click();
  a.remove();
};

watch(volume, applyVolume)

onMounted(() => {
    const a = audio.value
    a.preload = 'metadata'
    a.playbackRate = rate.value
    a.volume = volume.value
    a.muted = muted.value

    a.addEventListener('play', () => (playing.value = true))
    a.addEventListener('pause', () => (playing.value = false))
    a.addEventListener('ended', () => (playing.value = false))
    a.addEventListener('loadedmetadata', () => (duration.value = a.duration || 0))

    raf = requestAnimationFrame(update)
})

onBeforeUnmount(() => {
    cancelAnimationFrame(raf)
    audio.value?.pause()
})

watch(() => props.url, () => {
    // reset state on new URL
    playing.value = false
    current.value = 0
    progress.value = 0
})
</script>
