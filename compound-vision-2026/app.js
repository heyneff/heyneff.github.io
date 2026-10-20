const MAX_TILES = 36;
const DEFAULT_TILES = 9;

const $ = (id) => document.getElementById(id);
const stage = $('stage');
const tilesEl = $('tiles');
const controls = $('controls');
const message = $('message');
const urlInput = $('url');
const stepInput = $('step');
const countInput = $('count');
const volumeInput = $('volume');
const playButton = $('play');

const state = {
  url: '',
  seed: 1,
  videos: [],
  playing: false,
  forceHidden: false,
};

let audioCtx = null;
let masterGain = null;
let mixNodes = [];
let hideTimer = null;

// Seeded PRNG (mulberry32) so a seed always produces the same layout.
function mulberry32(seed) {
  let a = seed;
  return function () {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function area(r) {
  return r.w * r.h;
}

// Recursively split the largest tile until there are `count` tiles.
// Split direction alternates on each cut; the cut position is seeded.
// Returns rects in unit coordinates, sorted left to right, top to bottom.
function layout(count, seed) {
  const rand = mulberry32(seed);
  const rects = [{ x: 0, y: 0, w: 1, h: 1 }];

  for (let k = 0; rects.length < count; k++) {
    let big = 0;
    for (let i = 1; i < rects.length; i++) {
      if (area(rects[i]) > area(rects[big])) big = i;
    }

    const r = rects[big];
    const t = 0.35 + rand() * 0.3;
    const vertical = k % 2 === 0;
    const a = vertical
      ? { x: r.x, y: r.y, w: r.w * t, h: r.h }
      : { x: r.x, y: r.y, w: r.w, h: r.h * t };
    const b = vertical
      ? { x: r.x + r.w * t, y: r.y, w: r.w * (1 - t), h: r.h }
      : { x: r.x, y: r.y + r.h * t, w: r.w, h: r.h * (1 - t) };

    rects.splice(big, 1, a, b);
  }

  return rects.sort((p, q) => p.y - q.y || p.x - q.x);
}

function stepSeconds() {
  const v = parseFloat(stepInput.value);
  return Number.isFinite(v) && v >= 0 ? v : 0;
}

// Tile i (0-based) plays at i × step past the shared clock, wrapped to the
// video's duration. The shared clock is the first tile's position.
function seekTo(video, index, clock = 0) {
  if (!Number.isFinite(video.duration) || video.duration <= 0) return;
  video.currentTime = (index * stepSeconds() + clock) % video.duration;
}

function sharedClock() {
  const master = state.videos[0];
  return master ? master.currentTime : 0;
}

function tileCount() {
  const n = parseInt(countInput.value, 10);
  const clamped = Number.isFinite(n) ? Math.min(MAX_TILES, Math.max(1, n)) : DEFAULT_TILES;
  countInput.value = clamped;
  return clamped;
}

function showMessage(text) {
  message.textContent = text;
  message.hidden = false;
}

function hideMessage() {
  message.hidden = true;
}

function ensureAudio() {
  if (audioCtx) return;
  const Ctx = window.AudioContext || window.webkitAudioContext;
  audioCtx = new Ctx();
  masterGain = audioCtx.createGain();
  masterGain.gain.value = parseFloat(volumeInput.value);
  masterGain.connect(audioCtx.destination);
}

// Each tile's audio goes through its own gain, scaled by tile count so the
// mix does not clip, then into the master gain.
function connectAudio() {
  if (!audioCtx) return;
  const scale = 1 / Math.sqrt(state.videos.length || 1);
  state.videos.forEach((video) => {
    const source = audioCtx.createMediaElementSource(video);
    const gain = audioCtx.createGain();
    gain.gain.value = scale;
    source.connect(gain);
    gain.connect(masterGain);
    mixNodes.push({ source, gain });
  });
}

function teardown() {
  stopResync();
  mixNodes.forEach(({ source, gain }) => {
    source.disconnect();
    gain.disconnect();
  });
  mixNodes = [];

  const old = state.videos;
  state.videos = [];
  tilesEl.innerHTML = '';

  old.forEach((video) => {
    video.pause();
    video.removeAttribute('src');
    video.load();
  });
}

function build() {
  teardown();
  if (!state.url) return;

  const count = tileCount();
  const rects = layout(count, state.seed);
  const crossOrigin = new URL(state.url, location.href).origin !== location.origin;

  rects.forEach((r, i) => {
    const tile = document.createElement('div');
    tile.className = 'tile';
    tile.style.left = `${r.x * 100}%`;
    tile.style.top = `${r.y * 100}%`;
    tile.style.width = `${r.w * 100}%`;
    tile.style.height = `${r.h * 100}%`;

    const video = document.createElement('video');
    video.playsInline = true;
    video.preload = 'auto';
    video.loop = false;
    if (crossOrigin) video.crossOrigin = 'anonymous';

    video.addEventListener('loadedmetadata', () => {
      seekTo(video, i);
      hideMessage();
      if (state.playing && state.videos.every((v) => v.readyState >= 1)) syncAndPlay();
    });

    // Wrap-around: when a tile reaches the end, it jumps back to its own offset.
    video.addEventListener('ended', () => {
      seekTo(video, i, sharedClock());
      if (state.playing) video.play().catch(() => {});
    });

    video.addEventListener('error', () => {
      if (!video.isConnected) return;
      showMessage(
        'Video failed to load. Check the URL. If it is hosted elsewhere, the server must send ' +
          'Access-Control-Allow-Origin, which the audio mix requires.'
      );
    });

    tile.append(video);
    tilesEl.append(tile);
    state.videos.push(video);

    video.src = state.url;
  });

  connectAudio();
}

// Seek every tile to its offset, wait for all seeks to land, then start them
// in the same tick so their startup lag does not skew the offsets.
let syncRun = 0;
let resyncTimer = null;

// Playback rates drift apart over time. Once a second, check each tile against
// the first tile's timeline and seek any that have fallen more than 0.15 s off.
function resync() {
  const master = state.videos[0];
  if (!master || master.paused || !Number.isFinite(master.duration)) return;

  const duration = master.duration;
  state.videos.forEach((video, i) => {
    if (i === 0 || video.paused || !Number.isFinite(video.duration)) return;

    const expected = (i * stepSeconds() + master.currentTime) % duration;
    // Signed circular difference, so a tile just past the wrap is not treated as far off.
    const diff = ((((video.currentTime - expected) + duration / 2) % duration) + duration) % duration - duration / 2;
    if (Math.abs(diff) > 0.15) video.currentTime = expected;
  });
}

function startResync() {
  stopResync();
  resyncTimer = setInterval(resync, 1000);
}

function stopResync() {
  clearInterval(resyncTimer);
  resyncTimer = null;
}

function syncAndPlay() {
  // Only the newest run may start playback; older runs are superseded.
  const run = ++syncRun;

  const seeks = state.videos.map((video, i) => {
    video.pause();
    if (!Number.isFinite(video.duration)) return Promise.resolve();

    const landed = new Promise((resolve) => {
      video.addEventListener('seeked', resolve, { once: true });
    });
    seekTo(video, i);
    // Don't let one stalled tile hold up the others.
    return Promise.race([landed, new Promise((resolve) => setTimeout(resolve, 1000))]);
  });

  Promise.all(seeks).then(() => {
    if (run !== syncRun || !state.playing) return;
    state.videos.forEach((video) => {
      video.play().then(hideMessage, () => {
        showMessage('Playback was blocked by the browser. Click the screen to start.');
      });
    });
    startResync();
  });
}

async function setPlaying(on) {
  state.playing = on;
  playButton.textContent = on ? 'Pause' : 'Play';

  if (!on) {
    stopResync();
    state.videos.forEach((video) => video.pause());
    return;
  }

  ensureAudio();
  if (audioCtx.state === 'suspended') {
    await audioCtx.resume().catch(() => {});
  }

  syncAndPlay();
}

function togglePlaying() {
  if (!state.url) return;
  setPlaying(!state.playing);
}

function applyVolume() {
  const v = parseFloat(volumeInput.value);
  if (masterGain) {
    masterGain.gain.value = v;
  } else {
    state.videos.forEach((video) => (video.volume = v));
  }
}

function loadUrl(raw) {
  const url = raw.trim();
  if (!url) return;

  try {
    new URL(url);
  } catch {
    showMessage('Enter a full URL, including https://');
    return;
  }

  state.url = url;
  urlInput.value = url;
  $('placeholder').hidden = true;
  hideMessage();
  ensureAudio();
  build();
  setPlaying(true);
}

function reshuffle() {
  state.seed = Math.floor(Math.random() * 2147483647) + 1;
  build();
  if (state.playing) setPlaying(true);
}

function setCount(n) {
  countInput.value = Math.min(MAX_TILES, Math.max(1, n));
  build();
  if (state.playing) setPlaying(true);
}

function toggleFullscreen() {
  if (document.fullscreenElement) {
    document.exitFullscreen();
  } else {
    document.documentElement.requestFullscreen?.().catch(() => {});
  }
}

function showControls() {
  controls.classList.remove('hidden');
  document.body.classList.remove('idle');
  clearTimeout(hideTimer);
  if (!state.forceHidden) {
    hideTimer = setTimeout(hideControls, 2000);
  }
}

function hideControls() {
  controls.classList.add('hidden');
  document.body.classList.add('idle');
}

// Events

$('load').addEventListener('click', () => loadUrl(urlInput.value));
urlInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') loadUrl(urlInput.value);
});

stepInput.addEventListener('change', () => {
  state.videos.forEach((video, i) => seekTo(video, i));
});

countInput.addEventListener('change', () => setCount(tileCount()));
volumeInput.addEventListener('input', applyVolume);
playButton.addEventListener('click', togglePlaying);
$('reshuffle').addEventListener('click', reshuffle);
$('fullscreen').addEventListener('click', toggleFullscreen);

// Clicking the screen starts playback if it was blocked by autoplay rules.
stage.addEventListener('pointerdown', () => {
  if (!state.playing && state.videos.length) setPlaying(true);
});

controls.addEventListener('pointerenter', () => clearTimeout(hideTimer));
controls.addEventListener('pointerleave', showControls);

window.addEventListener('mousemove', () => {
  if (!state.forceHidden) showControls();
});

window.addEventListener('keydown', (e) => {
  if (e.ctrlKey || e.metaKey || e.altKey) return;
  if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

  switch (e.key) {
    case ' ':
      e.preventDefault();
      togglePlaying();
      break;
    case 'f':
    case 'F':
      toggleFullscreen();
      break;
    case 'r':
    case 'R':
      reshuffle();
      break;
    case '+':
    case '=':
      setCount(tileCount() + 1);
      break;
    case '-':
    case '_':
      setCount(tileCount() - 1);
      break;
    case 'h':
    case 'H':
      state.forceHidden = !state.forceHidden;
      if (state.forceHidden) hideControls();
      else showControls();
      break;
  }
});

// Start

const initial = new URLSearchParams(location.search).get('v');
if (initial) {
  urlInput.value = initial;
  loadUrl(initial);
}
showControls();
