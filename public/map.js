const CATEGORIES = (window.KOSTAA_CATEGORIES && Object.keys(window.KOSTAA_CATEGORIES).length)
  ? window.KOSTAA_CATEGORIES
  : {
      seeking: 'Търся',
      lost: 'Загубено',
      warning: 'Внимание',
      free: 'Безплатно',
      other: 'Друго',
    };

function categoryLabel(id) {
  return CATEGORIES[id] || '';
}
const REVEAL_DELTA = 0.012;
const LINK_DELTA = 0.005;
const CITIES = [
  { name: 'София', find: 'sofia sofiq', latitude: 42.6977, longitude: 23.3219 },
  { name: 'Пловдив', find: 'plovdiv', latitude: 42.1354, longitude: 24.7453 },
  { name: 'Варна', find: 'varna', latitude: 43.2141, longitude: 27.9147 },
  { name: 'Бургас', find: 'burgas', latitude: 42.5048, longitude: 27.4626 },
  { name: 'Стара Загора', find: 'stara zagora', latitude: 42.4258, longitude: 25.6345 },
  { name: 'Русе', find: 'ruse', latitude: 43.8356, longitude: 25.9657 },
  { name: 'Плевен', latitude: 43.417, longitude: 24.6067 },
  { name: 'Сливен', latitude: 42.6816, longitude: 26.3292 },
  { name: 'Добрич', latitude: 43.5726, longitude: 27.8273 },
  { name: 'Шумен', latitude: 43.2712, longitude: 26.9361 },
  { name: 'Перник', latitude: 42.6052, longitude: 23.0378 },
  { name: 'Хасково', latitude: 41.9344, longitude: 25.5554 },
  { name: 'Ямбол', latitude: 42.4842, longitude: 26.5035 },
  { name: 'Пазарджик', latitude: 42.1928, longitude: 24.3336 },
  { name: 'Благоевград', latitude: 42.0119, longitude: 23.09 },
  { name: 'Велико Търново', latitude: 43.0757, longitude: 25.6172 },
  { name: 'Враца', latitude: 43.2102, longitude: 23.5529 },
  { name: 'Габрово', latitude: 42.8742, longitude: 25.3187 },
  { name: 'Видин', latitude: 43.9916, longitude: 22.8824 },
  { name: 'Монтана', latitude: 43.4125, longitude: 23.225 },
  { name: 'Ловеч', latitude: 43.137, longitude: 24.7142 },
  { name: 'Кърджали', latitude: 41.6338, longitude: 25.3777 },
  { name: 'Кюстендил', latitude: 42.2839, longitude: 22.6911 },
  { name: 'Разград', latitude: 43.5332, longitude: 26.5242 },
  { name: 'Силистра', latitude: 44.1147, longitude: 27.2672 },
  { name: 'Търговище', latitude: 43.2494, longitude: 26.5727 },
  { name: 'Смолян', latitude: 41.5774, longitude: 24.7129 },
  { name: 'Казанлък', latitude: 42.6194, longitude: 25.393 },
];

const map = new maplibregl.Map({
  container: 'map',
  style: 'https://tiles.openfreemap.org/styles/liberty',
  center: [25.6345, 42.4258],
  zoom: 12.2,
  pitch: 48,
  bearing: -14,
  maxPitch: 75,
  minZoom: 3,
  maxZoom: 19,
  attributionControl: true,
});
map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'bottom-left');
let viewTouched = false;
let placed = false;
map.on('dragstart', () => {
  viewTouched = true;
});
map.getContainer().addEventListener('wheel', () => {
  viewTouched = true;
}, { passive: true });
map.on('zoomstart', (event) => {
  if (event.originalEvent) {
    viewTouched = true;
  }
});
map.on('rotatestart', (event) => {
  if (event.originalEvent) {
    viewTouched = true;
  }
});
map.on('pitchstart', (event) => {
  if (event.originalEvent) {
    viewTouched = true;
  }
});

const panel = document.getElementById('panel');
const nearbyEl = document.getElementById('nearby');
const banner = document.getElementById('banner');
const empty = document.getElementById('empty');
const replyText = document.getElementById('reply-text');

let pins = [];
let markers = [];
let selected = null;
const pageParams = new URLSearchParams(window.location.search);
let period = pageParams.get('period') === 'today' ? 'today' : '';
let desktopAhead = '10';
let listCity = '';
let phoneAhead = '4';
if (!window.matchMedia('(min-width: 801px)').matches) {
  const asked = pageParams.get('ahead');
  if (asked === '4' || asked === '10' || asked === 'all') {
    phoneAhead = asked;
  }
}
let replies = [];
let replyError = '';
let listCenter = null;
let requestId = 0;
let nearbyRequest = 0;
let nearbyRows = [];
let listCategory = '';
let loadTimer = 0;
let picking = false;
let relocating = false;
let placingId = null;
let unassignedRows = [];
let draftMarker = null;

function deviceId() {
  const key = 'blip-device-id';
  let id = localStorage.getItem(key);
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem(key, id);
  }
  return id;
}

function headers() {
  return {
    Accept: 'application/json',
    'X-Device-Id': deviceId(),
  };
}

function sofiaClock(utcMs) {
  const fmt = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Europe/Sofia',
    hourCycle: 'h23',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
  const parts = {};
  fmt.formatToParts(new Date(utcMs)).forEach((part) => {
    parts[part.type] = part.value;
  });
  return Date.UTC(+parts.year, +parts.month - 1, +parts.day, +parts.hour, +parts.minute, +parts.second);
}

function sofiaDayRange(now = new Date()) {
  const fmt = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Europe/Sofia',
    hourCycle: 'h23',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });
  const parts = {};
  fmt.formatToParts(now).forEach((part) => {
    parts[part.type] = part.value;
  });
  const midnight = Date.UTC(+parts.year, +parts.month - 1, +parts.day, 0, 0, 0);
  const nextMidnight = Date.UTC(+parts.year, +parts.month - 1, +parts.day + 1, 0, 0, 0);
  const start = midnight - (sofiaClock(midnight) - midnight);
  const end = nextMidnight - (sofiaClock(nextMidnight) - nextMidnight);
  return { start, end: end - 1 };
}

function occursToday(pin) {
  const startAt = Date.parse(pin.starts_at || pin.created_at);
  const endAt = Date.parse(pin.expires_at);
  if (!Number.isFinite(startAt) || !Number.isFinite(endAt)) {
    return false;
  }
  const day = sofiaDayRange();
  return startAt <= day.end && endAt > day.start;
}

function desktopMap() {
  return window.matchMedia('(min-width: 801px)').matches;
}

function startsWithinDays(pin, days, now = Date.now()) {
  if (!pin.starts_at) {
    return true;
  }
  const start = Date.parse(pin.starts_at);
  if (!Number.isFinite(start) || start <= now) {
    return true;
  }
  return start <= now + days * 24 * 60 * 60 * 1000;
}

const CITY_RADIUS_KM = 18;

function cityNameFor(pin) {
  let best = '';
  let bestKm = CITY_RADIUS_KM;
  CITIES.forEach((city) => {
    const km = kmBetween(pin.latitude, pin.longitude, city.latitude, city.longitude);
    if (km <= bestKm) {
      bestKm = km;
      best = city.name;
    }
  });
  return best;
}

function matchesDesktopFilters(pin) {
  if (!desktopMap()) {
    return true;
  }
  if (listCategory && pin.category !== listCategory) {
    return false;
  }
  if (listCity && cityNameFor(pin) !== listCity) {
    return false;
  }
  return true;
}

function phoneWindowMs() {
  if (phoneAhead === 'all') {
    return null;
  }
  return Number(phoneAhead) * 24 * 60 * 60 * 1000;
}

function pinInWindow(pin, now = Date.now()) {
  if (desktopMap()) {
    if (!matchesDesktopFilters(pin)) {
      return false;
    }
    if (period === 'today') {
      return occursToday(pin);
    }
    if (desktopAhead === 'all') {
      return true;
    }
    return pin.id === selected?.id || startsWithinDays(pin, Number(desktopAhead), now);
  }
  const limit = phoneWindowMs();
  if (limit == null || pin.id === selected?.id || !pin.starts_at) {
    return true;
  }
  const start = Date.parse(pin.starts_at);
  if (!Number.isFinite(start) || start <= now) {
    return true;
  }
  return start <= now + limit;
}

function shownPins() {
  const now = Date.now();
  return pins.filter((pin) => pinInWindow(pin, now));
}

function cityBySlug(slug) {
  const table = window.KOSTAA_CITY_SLUGS || {};
  return CITIES.find((city) => table[city.name] === slug) || null;
}

function withPeriod(params) {
  if (desktopMap()) {
    if (period === 'today') {
      params.set('period', 'today');
    } else if (desktopAhead !== 'all') {
      params.set('ahead', String(desktopAhead));
    }
    return params;
  }
  if (phoneAhead !== 'all') {
    params.set('ahead', phoneAhead);
  }
  return params;
}

function placeFilterTools() {
  const tools = document.getElementById('nearby-tools');
  if (!tools) {
    return;
  }
  if (desktopMap()) {
    document.getElementById('filter-mount').append(tools);
    return;
  }
  const status = document.getElementById('nearby-status');
  status.parentElement.insertBefore(tools, status);
}

function setPinsOpen(open) {
  placeFilterTools();
  nearbyEl.hidden = !open;
  const filters = document.getElementById('map-filters');
  const split = open && desktopMap();
  filters.hidden = !split;
  nearbyEl.classList.toggle('split', split);
}

window.matchMedia('(min-width: 801px)').addEventListener('change', () => {
  placeFilterTools();
  if (!nearbyEl.hidden) {
    setPinsOpen(true);
  } else {
    document.getElementById('map-filters').hidden = true;
    nearbyEl.classList.remove('split');
  }
  loadPins();
  const radius = document.getElementById('radius');
  if (radius && listCenter) {
    loadNearby(Number(radius.value));
  }
});

function paintPhoneAhead() {
  document.querySelectorAll('#phone-time button').forEach((button) => {
    const on = button.dataset.ahead === phoneAhead;
    button.classList.toggle('on', on);
    button.setAttribute('aria-pressed', on ? 'true' : 'false');
  });
}

async function readError(response) {
  try {
    const body = await response.json();
    const errors = body.errors || {};
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) {
      return first[0];
    }
    return body.message || 'Нещо се обърка.';
  } catch {
    return 'Нещо се обърка.';
  }
}

function currentRegion() {
  const center = map.getCenter();
  const zoom = map.getZoom();
  const canvas = map.getCanvas();
  const height = Math.max(canvas.clientHeight, 1);
  const width = Math.max(canvas.clientWidth, 1);
  const cos = Math.cos(center.lat * Math.PI / 180);
  const metersPerPixel = 156543.03392 * Math.max(cos, 0.01) / (2 ** zoom);
  const latitudeDelta = (height * metersPerPixel) / 111320;
  const longitudeDelta = (width * metersPerPixel) / (111320 * Math.max(Math.abs(cos), 0.2));
  return {
    latitude: center.lat,
    longitude: center.lng,
    latitudeDelta,
    longitudeDelta,
  };
}

function timeLeft(expiresAt, now = Date.now()) {
  const ms = new Date(expiresAt).getTime() - now;
  if (!Number.isFinite(ms) || ms <= 0) {
    return 'Изтече';
  }
  const minutes = Math.max(1, Math.ceil(ms / 60000));
  const days = Math.floor(minutes / 1440);
  const hours = Math.floor((minutes % 1440) / 60);
  const rest = minutes % 60;
  if (days === 0) {
    if (hours === 0) {
      return `още ${rest} мин`;
    }
    return rest === 0 ? `още ${hours} ч` : `още ${hours} ч ${rest} мин`;
  }
  const dayLabel = days === 1 ? '1 ден' : `${days} дни`;
  if (hours === 0 && rest === 0) {
    return `още ${dayLabel}`;
  }
  if (hours === 0) {
    return `още ${dayLabel} и ${rest} мин`;
  }
  const hourLabel = hours === 1 ? '1 час' : `${hours} часа`;
  return `още ${dayLabel} и ${hourLabel}`;
}

function sofiaParts(date) {
  return new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Sofia',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date);
}

function sofiaPart(parts, type) {
  const value = parts.find((part) => part.type === type)?.value || '';
  return type === 'hour' && value === '24' ? '00' : value;
}

function clockInSofia(date) {
  const parts = sofiaParts(date);
  const hour = sofiaPart(parts, 'hour');
  const minute = sofiaPart(parts, 'minute');
  if (hour === '' || minute === '') {
    return '';
  }
  return `${hour}:${minute}`;
}

function sofiaDayKey(date) {
  const parts = sofiaParts(date);
  const year = sofiaPart(parts, 'year');
  const month = sofiaPart(parts, 'month');
  const day = sofiaPart(parts, 'day');
  if (year === '' || month === '' || day === '') {
    return '';
  }
  return `${year}-${month}-${day}`;
}

function relativeWhen(iso, now = Date.now()) {
  const date = new Date(iso);
  const ms = date.getTime() - now;
  if (!Number.isFinite(ms) || ms <= 0) {
    return '';
  }
  const startKey = sofiaDayKey(date);
  const nowKey = sofiaDayKey(new Date(now));
  if (startKey === '' || nowKey === '') {
    return '';
  }
  if (startKey === nowKey) {
    const minutes = Math.max(1, Math.round(ms / 60000));
    if (minutes < 60) {
      return `след ${minutes} мин`;
    }
    const hours = Math.max(1, Math.round(minutes / 60));
    return `след ${hours} ${hours === 1 ? 'час' : 'часа'}`;
  }
  const clock = clockInSofia(date);
  if (!clock) {
    return '';
  }
  const days = Math.round((Date.parse(`${startKey}T00:00:00Z`) - Date.parse(`${nowKey}T00:00:00Z`)) / 86400000);
  if (days <= 1) {
    return `утре в ${clock}`;
  }
  return `след ${days} дни в ${clock}`;
}

function upcomingSoon(pin, now = Date.now()) {
  if (!pin || pin.kind !== 'upcoming' || !pin.starts_at) {
    return false;
  }
  const ms = new Date(pin.starts_at).getTime() - now;
  return ms > 0 && ms < 24 * 60 * 60 * 1000;
}

function eventStarted(pin, now = Date.now()) {
  if (!pin || !pin.starts_at) {
    return false;
  }
  const start = new Date(pin.starts_at).getTime();
  return Number.isFinite(start) && start <= now;
}

function formatWhen(iso) {
  const date = new Date(iso);
  if (!Number.isFinite(date.getTime())) {
    return '';
  }
  return new Intl.DateTimeFormat('bg-BG', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function panelWhen(pin) {
  return upcomingLabel(pin) || timeLeft(pin.expires_at);
}

function bubbleWhen(pin, now = Date.now()) {
  const upcoming = upcomingLabel(pin);
  if (upcoming) {
    return upcoming;
  }
  if (!eventStarted(pin, now)) {
    return '';
  }
  const left = timeLeft(pin.expires_at, now);
  return left === 'Изтече' ? '' : left;
}

function timeHot(pin, now = Date.now()) {
  return upcomingSoon(pin, now) || (eventStarted(pin, now) && bubbleWhen(pin, now) !== '');
}

function pinStackHeight(pin) {
  const text = 34 + (categoryLabel(pin.category) ? 14 : 0) + (bubbleWhen(pin) ? 14 : 0);

  return text + 28;
}

const STACK_SIDE_BY_SIDE = 4;
const STACK_SHIFT = 124;
const STACK_FETCH_PAD_PX = 360;

function tailFor(index, count) {
  if (count < 2) {
    return 'center';
  }
  return index % 2 === 0 ? 'right' : 'left';
}

function placeGroup(group) {
  if (group.length < STACK_SIDE_BY_SIDE) {
    let y = 0;
    return group.map((pin, index) => {
      const placed = { ...pin, stackX: 0, stackY: y, tail: tailFor(index, group.length) };
      y += pinStackHeight(pin);
      return placed;
    });
  }

  const placed = [];
  let y = 0;
  for (let index = 0; index < group.length; index += 2) {
    const left = group[index];
    const right = group[index + 1];
    const rowHeight = Math.max(pinStackHeight(left), right ? pinStackHeight(right) : 0);
    if (!right) {
      placed.push({ ...left, stackX: 0, stackY: y, tail: tailFor(index, group.length) });
    } else {
      placed.push({ ...left, stackX: -STACK_SHIFT, stackY: y, tail: tailFor(index, group.length) });
      placed.push({ ...right, stackX: STACK_SHIFT, stackY: y, tail: tailFor(index + 1, group.length) });
    }
    y += rowHeight;
  }

  return placed;
}

function upcomingLabel(pin) {
  if (!pin || pin.kind !== 'upcoming' || !pin.starts_at) {
    return '';
  }
  if (new Date(pin.starts_at).getTime() <= Date.now()) {
    return '';
  }
  return relativeWhen(pin.starts_at);
}

function durationText(minutes) {
  if (minutes === 5) {
    return '5 мин';
  }
  if (minutes === 60) {
    return '1 час';
  }
  if (minutes === 1440) {
    return '24 часа';
  }
  return '';
}

function timeAgo(createdAt, now = Date.now()) {
  const ms = now - new Date(createdAt).getTime();
  if (!Number.isFinite(ms) || ms < 45000) {
    return 'Току-що';
  }
  const minutes = Math.floor(ms / 60000);
  if (minutes < 60) {
    return minutes === 1 ? 'преди 1 мин' : `преди ${minutes} мин`;
  }
  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return hours === 1 ? 'преди 1 час' : `преди ${hours} часа`;
  }
  const days = Math.floor(hours / 24);
  return days === 1 ? 'преди 1 ден' : `преди ${days} дни`;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function safeUrl(url) {
  return typeof url === 'string' && /^https?:\/\//i.test(url) ? escapeHtml(url) : '';
}

function kmBetween(fromLat, fromLng, toLat, toLng) {
  const latDelta = (toLat - fromLat) * Math.PI / 180;
  const lngDelta = (toLng - fromLng) * Math.PI / 180;
  const from = fromLat * Math.PI / 180;
  const to = toLat * Math.PI / 180;
  const haversine = Math.sin(latDelta / 2) ** 2
    + Math.cos(from) * Math.cos(to) * Math.sin(lngDelta / 2) ** 2;
  return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(haversine)));
}

function formatDistance(kilometers) {
  if (kilometers < 1) {
    return `${Math.max(1, Math.round(kilometers * 1000))} м`;
  }
  if (kilometers < 10) {
    return `${Math.round(kilometers * 10) / 10} км`;
  }
  return `${Math.round(kilometers)} км`;
}

function boxForRadius(latitude, longitude, kilometers) {
  const latitudeDelta = (kilometers * 2) / 111.32;
  const cosine = Math.cos(latitude * Math.PI / 180);
  return {
    latitude,
    longitude,
    latitudeDelta,
    longitudeDelta: latitudeDelta / Math.max(0.2, Math.abs(cosine)),
  };
}

function sameSpot(a, b) {
  return Math.abs(a.latitude - b.latitude) < 0.00015
    && Math.abs(a.longitude - b.longitude) < 0.00015;
}

function groupPins(rows, region) {
  if (!rows.length || !region || !region.latitudeDelta) {
    return { clusters: [], singles: [] };
  }
  if (region.latitudeDelta <= REVEAL_DELTA) {
    const sorted = [...rows].sort((a, b) => a.id - b.id);
    const groups = [];
    sorted.forEach((pin) => {
      let group = groups.find((item) => sameSpot(item, pin));
      if (!group) {
        group = { latitude: pin.latitude, longitude: pin.longitude, pins: [] };
        groups.push(group);
      }
      group.pins.push(pin);
    });
    return { clusters: [], singles: groups.flatMap((group) => placeGroup(group.pins)) };
  }

  const latCell = region.latitudeDelta / 7;
  const lngCell = (region.longitudeDelta || region.latitudeDelta) / 7;
  const cells = new Map();
  rows.forEach((pin) => {
    const key = `${Math.floor(pin.latitude / latCell)}:${Math.floor(pin.longitude / lngCell)}`;
    const group = cells.get(key);
    if (group) {
      group.push(pin);
    } else {
      cells.set(key, [pin]);
    }
  });

  const clusters = [];
  const singles = [];
  const openId = selected && selected.id ? selected.id : 0;
  cells.forEach((group, key) => {
    let members = group;
    if (openId) {
      const open = group.find((pin) => pin.id === openId);
      if (open && group.length > 1) {
        singles.push({ ...open, stackX: 0, stackY: 0, tail: 'center' });
        members = group.filter((pin) => pin.id !== openId);
      }
    }
    if (members.length === 0) {
      return;
    }
    if (members.length === 1) {
      singles.push({ ...members[0], stackX: 0, stackY: 0, tail: 'center' });
      return;
    }
    const lats = members.map((pin) => pin.latitude);
    const lngs = members.map((pin) => pin.longitude);
    clusters.push({
      id: key,
      count: members.length,
      latitude: lats.reduce((sum, value) => sum + value, 0) / members.length,
      longitude: lngs.reduce((sum, value) => sum + value, 0) / members.length,
      latitudeSpan: Math.max(...lats) - Math.min(...lats),
      longitudeSpan: Math.max(...lngs) - Math.min(...lngs),
    });
  });
  return { clusters, singles };
}

function zoomRegion(region, cluster) {
  const span = Math.max(cluster.latitudeSpan || 0, cluster.longitudeSpan || 0);
  const latitudeDelta = Math.min(Math.max(span * 2.4, 0.004), REVEAL_DELTA - 0.001);
  const ratio = region && region.longitudeDelta && region.latitudeDelta
    ? region.longitudeDelta / region.latitudeDelta
    : 1;
  return {
    latitude: cluster.latitude,
    longitude: cluster.longitude,
    latitudeDelta,
    longitudeDelta: latitudeDelta * ratio,
  };
}

function zoomForDelta(latitude, latitudeDelta) {
  const height = Math.max(map.getCanvas().clientHeight, 1);
  const metersPerPixel = (latitudeDelta * 111320) / height;
  const cos = Math.cos(latitude * Math.PI / 180);
  const zoom = Math.log2((156543.03392 * Math.max(cos, 0.01)) / metersPerPixel);
  return Math.max(0, Math.min(18, zoom));
}

function frameMap(west, south, east, north, duration, padding) {
  placed = true;
  map.fitBounds([[west, south], [east, north]], {
    pitch: Math.max(map.getPitch(), 48),
    bearing: map.getBearing(),
    maxZoom: 17,
    duration: duration ?? 700,
    padding: padding || 0,
  });
}

function showBounds(region) {
  placed = true;
  map.easeTo({
    center: [region.longitude, region.latitude],
    zoom: zoomForDelta(region.latitude, region.latitudeDelta),
    pitch: map.getPitch(),
    bearing: map.getBearing(),
    duration: 700,
  });
}

function markerElement(html, onClick) {
  const element = document.createElement('div');
  element.className = 'blip-icon';
  element.innerHTML = html;
  element.addEventListener('click', (event) => {
    event.stopPropagation();
    if (picking || placingId || relocating) {
      return;
    }
    onClick();
  });
  return element;
}

function fitBubblePhoto(element) {
  const image = element.querySelector('.bubble img');
  const meta = element.querySelector('.bubble .meta');
  if (!image || !meta) {
    return;
  }
  const apply = () => {
    const height = meta.offsetHeight;
    if (height > 0) {
      image.style.width = `${height}px`;
      image.style.height = `${height}px`;
    }
  };
  apply();
  if (document.fonts && document.fonts.status !== 'loaded') {
    document.fonts.ready.then(apply).catch(() => {});
  }
}

function drawMarkers() {
  markers.forEach((marker) => marker.remove());
  markers = [];
  const items = groupPins(shownPins(), currentRegion());

  items.clusters.forEach((cluster) => {
    const width = Math.max(46, 16 + String(cluster.count).length * 12);
    const element = markerElement(
      `<div class="count" style="width:${width}px">(${cluster.count})</div>`,
      () => showBounds(zoomRegion(currentRegion(), cluster)),
    );
    const marker = new maplibregl.Marker({ element, anchor: 'center' })
      .setLngLat([cluster.longitude, cluster.latitude])
      .addTo(map);
    markers.push(marker);
  });

  items.singles.forEach((pin) => {
    const kind = categoryLabel(pin.category);
    const when = bubbleWhen(pin);
    const image = safeUrl(pin.image_url)
      ? `<img src="${safeUrl(pin.image_url)}" alt="">`
      : '';
    const kindHtml = kind ? `<small>${escapeHtml(kind)}</small>` : '';
    const whenHtml = when ? `<small class="${timeHot(pin) ? 'soon' : ''}">${escapeHtml(when)}</small>` : '';
    const tailClass = pin.tail === 'right' || pin.tail === 'left' ? ` tail-${pin.tail}` : '';
    const element = markerElement(
      `<div class="bubble-wrap"><div class="bubble-card${tailClass}"><div class="bubble">${image}<div class="meta"><span>${escapeHtml(pin.message)}</span>${kindHtml}${whenHtml}</div></div><div class="tail"></div></div></div>`,
      () => {
        const region = currentRegion();
        const close = region && region.latitudeDelta <= REVEAL_DELTA;
        if (!close || items.singles.length < 2) {
          focusPin(pin, { padPanel: true });
        }
        openPin(pin);
      },
    );
    const marker = new maplibregl.Marker({
      element,
      anchor: 'bottom',
      offset: [pin.stackX || 0, -(pin.stackY || 0)],
    })
      .setLngLat([pin.longitude, pin.latitude])
      .addTo(map);
    fitBubblePhoto(element);
    marker.getElement().style.zIndex = String(1000 + (pin.stackY || 0) + (pin.stackX > 0 ? 1 : 0));
    markers.push(marker);
  });
  drawEdges();
}

function spreadEdges(chips) {
  const sorted = [...chips].sort((a, b) => a.angle - b.angle);
  for (let pass = 0; pass < 4; pass += 1) {
    for (let index = 1; index < sorted.length; index += 1) {
      const prev = sorted[index - 1];
      const item = sorted[index];
      const dx = item.left - prev.left;
      const dy = item.top - prev.top;
      const dist = Math.hypot(dx, dy);
      if (dist >= 46 || dist === 0) {
        continue;
      }
      const push = (46 - dist) / 2;
      const nx = dx / dist;
      const ny = dy / dist;
      prev.left -= nx * push;
      prev.top -= ny * push;
      item.left += nx * push;
      item.top += ny * push;
    }
  }
  return sorted;
}

function drawEdges() {
  const root = document.getElementById('edges');
  if (!root) {
    return;
  }
  root.replaceChildren();
  const region = currentRegion();
  if (!placed || !region || region.latitudeDelta <= REVEAL_DELTA || region.latitudeDelta > 0.16) {
    return;
  }
  const canvas = map.getCanvas();
  const width = canvas.clientWidth;
  const height = canvas.clientHeight;
  if (width < 20 || height < 20) {
    return;
  }
  const pad = desktopMap()
    ? { top: 72, right: 72, bottom: 110, left: 72 }
    : { top: 64, right: 36, bottom: 190, left: 36 };
  const chips = [];
  groupPins(pins, region).clusters.forEach((cluster) => {
    const point = map.project([cluster.longitude, cluster.latitude]);
    if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.y)) {
      return;
    }
    const inside = point.x >= pad.left && point.x <= width - pad.right
      && point.y >= pad.top && point.y <= height - pad.bottom;
    if (inside) {
      return;
    }
    const dx = point.x - width / 2;
    const dy = point.y - height / 2;
    if (Math.abs(dx) > width * 1.45 || Math.abs(dy) > height * 1.45) {
      return;
    }
    const cx = width / 2;
    const cy = height / 2;
    const scaleX = dx === 0 ? Infinity : ((dx > 0 ? width - pad.right : pad.left) - cx) / dx;
    const scaleY = dy === 0 ? Infinity : ((dy > 0 ? height - pad.bottom : pad.top) - cy) / dy;
    const scale = Math.min(scaleX, scaleY);
    if (!Number.isFinite(scale) || scale <= 0 || scale >= 1) {
      return;
    }
    chips.push({
      cluster,
      left: cx + dx * scale,
      top: cy + dy * scale,
      angle: Math.atan2(dy, dx),
    });
  });
  spreadEdges(chips).forEach((chip) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'edge-chip';
    button.style.left = `${chip.left}px`;
    button.style.top = `${chip.top}px`;
    const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    arrow.setAttribute('viewBox', '0 0 18 14');
    arrow.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M1 7h10M7 2l7 5-7 5');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    arrow.append(path);
    arrow.style.transform = `rotate(${chip.angle}rad)`;
    const label = document.createElement('span');
    label.textContent = `(${chip.cluster.count})`;
    if (Math.cos(chip.angle) > 0.35) {
      button.append(label, arrow);
    } else {
      button.append(arrow, label);
    }
    button.addEventListener('click', () => {
      showBounds(zoomRegion(currentRegion(), chip.cluster));
    });
    root.append(button);
  });
}

map.on('move', () => {
  if (placed) {
    drawEdges();
  }
});

function covers(pin, box) {
  return pin.latitude <= box.north
    && pin.latitude >= box.south
    && pin.longitude <= box.east
    && pin.longitude >= box.west;
}

function mergePins(existing, incoming, box, now) {
  const ids = new Set(incoming.map((pin) => pin.id));
  const byId = new Map();

  existing.forEach((pin) => {
    if (pin.latitude == null || pin.longitude == null) {
      return;
    }
    if (new Date(pin.expires_at).getTime() <= now) {
      return;
    }
    if (covers(pin, box) && !ids.has(pin.id)) {
      return;
    }
    byId.set(pin.id, pin);
  });

  incoming.forEach((pin) => {
    byId.set(pin.id, { ...byId.get(pin.id), ...pin });
  });

  return [...byId.values()];
}

async function loadPins() {
  if (!placed) {
    return;
  }
  const id = ++requestId;
  const bounds = map.getBounds();
  const region = currentRegion();
  let north = bounds.getNorth();
  let south = bounds.getSouth();
  let east = bounds.getEast();
  let west = bounds.getWest();
  if (region.latitudeDelta <= REVEAL_DELTA) {
    const height = map.getCanvas().clientHeight || 800;
    north += (north - south) * (STACK_FETCH_PAD_PX / height);
  } else if (region.latitudeDelta <= 0.16) {
    const latPad = Math.min((north - south) * 0.8, 0.045);
    const lngPad = Math.min((east - west) * 0.8, 0.045);
    north += latPad;
    south -= latPad;
    east += lngPad;
    west -= lngPad;
  }
  const box = { north, south, east, west };
  const params = withPeriod(new URLSearchParams({
    north: String(box.north),
    south: String(box.south),
    east: String(box.east),
    west: String(box.west),
  }));

  try {
    const response = await fetch(`/api/pins?${params}`, { headers: headers() });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (id !== requestId) {
      return;
    }
    const now = Date.now();
    const incoming = (body.pins || []).filter((pin) => (
      pin.latitude != null
      && pin.longitude != null
      && pin.kind !== 'unassigned'
      && new Date(pin.expires_at).getTime() > now
    ));
    pins = mergePins(pins, incoming, box, now);
    if (
      selected
      && new Date(selected.expires_at).getTime() > now
      && covers(selected, box)
      && !pins.some((pin) => pin.id === selected.id)
    ) {
      pins.push(selected);
    }
    banner.hidden = true;
    const view = {
      north: bounds.getNorth(),
      south: bounds.getSouth(),
      east: bounds.getEast(),
      west: bounds.getWest(),
    };
    empty.hidden = shownPins().some((pin) => covers(pin, view));
    if (selected) {
      const fresh = pins.find((pin) => pin.id === selected.id);
      if (fresh) {
        selected = { ...selected, ...fresh };
        renderPanel();
      } else if (covers(selected, box)) {
        closePanel();
      }
    }
    drawMarkers();
  } catch {
    if (id === requestId) {
      banner.hidden = false;
      empty.hidden = true;
    }
  }
}

function scheduleLoad() {
  clearTimeout(loadTimer);
  loadTimer = setTimeout(loadPins, 400);
}

function renderPanel() {
  if (!selected) {
    return;
  }
  document.getElementById('panel-message').textContent = selected.message;
  const category = document.getElementById('panel-category');
  const label = categoryLabel(selected.category);
  category.hidden = !label;
  category.textContent = label;
  const when = document.getElementById('panel-time');
  const whenText = panelWhen(selected);
  when.hidden = !whenText;
  when.textContent = whenText;
  when.classList.toggle('soon', timeHot(selected));
  const photo = document.getElementById('panel-photo');
  photo.hidden = !safeUrl(selected.image_url);
  photo.src = photo.hidden ? '' : selected.image_url;
  const story = document.getElementById('panel-description');
  story.hidden = !selected.description;
  story.textContent = selected.description || '';

  const replyBox = document.getElementById('panel-replies');
  replyBox.replaceChildren();
  const error = document.getElementById('panel-reply-error');
  error.hidden = !replyError;
  error.textContent = replyError;

  replies.forEach((reply) => {
    const block = document.createElement('div');
    block.className = reply.mine ? 'reply mine' : 'reply';
    const message = document.createElement('p');
    message.textContent = reply.message;
    const when = document.createElement('time');
    when.textContent = timeAgo(reply.created_at);
    block.append(message, when);
    if (reply.mine || reply.author_only) {
      const only = document.createElement('span');
      only.className = 'only';
      only.textContent = reply.mine
        ? (reply.author_only ? 'Ти · само за автора' : 'Ти')
        : 'Само за теб';
      block.append(only);
    }
    replyBox.append(block);
  });

  const imported = Boolean(selected.imported);
  const ban = document.getElementById('ban-device');
  if (ban) {
    ban.hidden = imported;
  }
  document.getElementById('panel-sent').hidden = imported || selected.mine || !selected.replied;
  document.getElementById('reply-form').hidden = imported || selected.mine || selected.replied;
  document.getElementById('report').hidden = imported || selected.mine || selected.reported;
  document.getElementById('reported').hidden = imported || selected.mine || !selected.reported;
  document.getElementById('remove').hidden = !selected.mine || imported;
}

function stopRelocatePick() {
  relocating = false;
  const button = document.getElementById('relocate-pick');
  if (button) {
    button.textContent = 'Избери на картата';
  }
  if (!picking && !placingId) {
    map.getCanvas().style.cursor = '';
  }
}

function closeRelocate() {
  stopRelocatePick();
  const box = document.getElementById('relocate');
  if (box) {
    box.hidden = true;
  }
}

async function openPin(pin) {
  closeRelocate();
  selected = pin;
  replies = [];
  replyError = '';
  replyText.value = '';
  document.getElementById('reply-private').checked = false;
  document.getElementById('reply-count').textContent = '0/200';
  document.getElementById('reply-form-error').hidden = true;
  document.getElementById('copy-status').hidden = true;
  setPinsOpen(false);
  panel.hidden = false;
  const scroller = panel.querySelector('.pin-scroll');
  if (scroller) {
    scroller.scrollTop = 0;
  }
  renderPanel();

  if (pin.imported) {
    return;
  }

  try {
    const response = await fetch(`/api/pins/${pin.id}/replies`, { headers: headers() });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (!selected || selected.id !== pin.id) {
      return;
    }
    replies = body.replies || [];
    renderPanel();
  } catch {
    if (selected && selected.id === pin.id) {
      replyError = 'Отговорите не се заредиха.';
      renderPanel();
    }
  }
}

function closePanel() {
  closeRelocate();
  const wasOpen = !panel.hidden;
  selected = null;
  replies = [];
  panel.hidden = true;
  if (wasOpen) {
    if (viewBeforePin) {
      restoreView();
    } else {
      map.setPadding({ top: 0, right: 0, bottom: 0, left: 0 });
    }
  }
}

function dropPinFromMap(id) {
  pins = pins.filter((pin) => pin.id !== id);
  empty.hidden = pins.length !== 0;
  closePanel();
  drawMarkers();
}

function pinUrl(pin) {
  const url = new URL('/map', window.location.origin);
  url.searchParams.set('pin', String(pin.id));
  return url.toString();
}

async function copyText(value) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return true;
    }
  } catch {
    // The textarea fallback covers a page that is not a secure context.
  }
  try {
    const area = document.createElement('textarea');
    area.value = value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    document.body.append(area);
    area.select();
    const copied = document.execCommand('copy');
    area.remove();
    return copied;
  } catch {
    return false;
  }
}

async function copyPinLink(pin, button) {
  const url = pinUrl(pin);
  const copied = await copyText(url);
  if (button) {
    const label = button.dataset.label;
    button.textContent = copied ? 'Копирано' : 'Не се копира';
    setTimeout(() => {
      button.textContent = label;
    }, 1600);
    return;
  }
  const status = document.getElementById('copy-status');
  status.hidden = false;
  status.textContent = copied ? 'Връзката е копирана.' : url;
}

async function sharePin(pin, button) {
  const url = pinUrl(pin);
  if (navigator.share) {
    try {
      await navigator.share({ title: pin.message, text: pin.message, url });
      return;
    } catch (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
    }
  }
  await copyPinLink(pin, button);
}

document.getElementById('panel-close').addEventListener('click', closePanel);
document.getElementById('copy-link').addEventListener('click', () => {
  if (selected) {
    copyPinLink(selected);
  }
});
document.getElementById('share-link').addEventListener('click', () => {
  if (selected) {
    sharePin(selected);
  }
});

replyText.addEventListener('input', () => {
  document.getElementById('reply-count').textContent = `${replyText.value.trim().length}/200`;
});

document.getElementById('reply-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const text = replyText.value.trim();
  const error = document.getElementById('reply-form-error');
  if (!text || !selected) {
    return;
  }
  const send = document.getElementById('reply-send');
  send.disabled = true;
  try {
    const response = await fetch(`/api/pins/${selected.id}/replies`, {
      method: 'POST',
      headers: { ...headers(), 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: text,
        author_only: document.getElementById('reply-private').checked,
      }),
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const created = await response.json();
    selected = { ...selected, replied: true };
    replies = [...replies.filter((item) => item.id !== created.id), { ...created, mine: true }];
    replyText.value = '';
    document.getElementById('reply-private').checked = false;
    error.hidden = true;
    renderPanel();
  } catch (err) {
    error.hidden = false;
    error.textContent = err.message || 'Отговорът не е изпратен.';
  } finally {
    send.disabled = false;
  }
});

async function reportPin(pin) {
  if (!pin || pin.imported || pin.mine || pin.reported) {
    return;
  }
  if (!window.confirm('Докладваш ли този пин? Пет доклада от различни хора го махат.')) {
    return;
  }
  try {
    const response = await fetch(`/api/pins/${pin.id}/report`, {
      method: 'POST',
      headers: headers(),
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (body.deleted) {
      nearbyRows = nearbyRows.filter((row) => row.id !== pin.id);
      dropPinFromMap(pin.id);
      renderNearbyList();
      return;
    }
    nearbyRows = nearbyRows.map((row) => (row.id === pin.id ? { ...row, reported: true } : row));
    if (selected && selected.id === pin.id) {
      selected = { ...selected, reported: true };
      renderPanel();
    }
    renderNearbyList();
  } catch (err) {
    window.alert(err.message || 'Пинът не можа да бъде докладван.');
  }
}

document.getElementById('report').addEventListener('click', () => {
  reportPin(selected);
});

document.getElementById('remove').addEventListener('click', async () => {
  if (!selected || !window.confirm('Махаш ли този пин?')) {
    return;
  }
  try {
    const response = await fetch(`/api/pins/${selected.id}`, {
      method: 'DELETE',
      headers: headers(),
    });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    dropPinFromMap(selected.id);
  } catch (err) {
    window.alert(err.message || 'Пинът не можа да бъде махнат.');
  }
});

async function loadNearby(kilometers) {
  if (!listCenter) {
    return;
  }
  const id = ++nearbyRequest;
  const status = document.getElementById('nearby-status');
  const list = document.getElementById('nearby-list');
  status.textContent = 'Зарежда се...';
  const region = boxForRadius(listCenter.lat, listCenter.lng, kilometers);
  const params = withPeriod(new URLSearchParams({
    north: String(region.latitude + region.latitudeDelta / 2),
    south: String(region.latitude - region.latitudeDelta / 2),
    east: String(region.longitude + region.longitudeDelta / 2),
    west: String(region.longitude - region.longitudeDelta / 2),
  }));

  try {
    const response = await fetch(`/api/pins?${params}`, { headers: headers() });
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    if (id !== nearbyRequest) {
      return;
    }
    nearbyRows = (body.pins || [])
      .map((pin) => ({
        ...pin,
        km: kmBetween(listCenter.lat, listCenter.lng, pin.latitude, pin.longitude),
      }))
      .filter((pin) => pin.km <= kilometers + 0.05)
      .sort((a, b) => a.km - b.km);
    renderNearbyList();
  } catch (err) {
    if (id === nearbyRequest) {
      nearbyRows = [];
      list.replaceChildren();
      status.textContent = err.message || 'Списъкът не се зареди.';
    }
  }
}

let listMenuPin = null;

function closeListMenu() {
  document.getElementById('list-menu').hidden = true;
  listMenuPin = null;
}

function toggleListMenu(anchor, pin) {
  const menu = document.getElementById('list-menu');
  if (listMenuPin && listMenuPin.id === pin.id && !menu.hidden) {
    closeListMenu();
    return;
  }
  listMenuPin = pin;
  const report = menu.querySelector('[data-act="report"]');
  const canReport = !pin.imported && !pin.mine;
  report.hidden = !canReport;
  report.disabled = Boolean(pin.reported);
  report.textContent = pin.reported ? 'Докладвано' : 'Докладвай';
  menu.hidden = false;
  const rect = anchor.getBoundingClientRect();
  const height = menu.offsetHeight;
  const top = rect.bottom + 6 + height > window.innerHeight - 8
    ? Math.max(8, rect.top - height - 6)
    : rect.bottom + 6;
  menu.style.top = `${top}px`;
  menu.style.left = `${Math.max(8, rect.right - menu.offsetWidth)}px`;
}

function renderNearbyList() {
  closeListMenu();
  const status = document.getElementById('nearby-status');
  const list = document.getElementById('nearby-list');
  const kilometers = Number(document.getElementById('radius').value);
  const now = Date.now();
  const rows = nearbyRows.filter((pin) => (
    (!listCategory || pin.category === listCategory)
    && pinInWindow(pin, now)
  ));
  list.replaceChildren();
  rows.forEach((pin) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pin-open';
      if (safeUrl(pin.image_url)) {
        const image = document.createElement('img');
        image.src = pin.image_url;
        image.alt = '';
        button.append(image);
      }
      const copy = document.createElement('span');
      const message = document.createElement('strong');
      message.textContent = pin.message;
      copy.append(message);
      const label = categoryLabel(pin.category);
      if (label) {
        const kind = document.createElement('span');
        kind.className = 'pin-category';
        kind.textContent = label;
        copy.append(kind);
      }
      const meta = document.createElement('small');
      const whenText = bubbleWhen(pin);
      meta.append(`${formatDistance(pin.km)} · `);
      if (whenText) {
        const time = document.createElement('span');
        time.textContent = whenText;
        if (timeHot(pin)) {
          time.className = 'soon';
        }
        meta.append(time);
      } else {
        meta.append(timeLeft(pin.expires_at));
      }
      copy.append(meta);
      button.append(copy);
      button.addEventListener('click', () => {
        closeListMenu();
        setPinsOpen(false);
        if (focusPin(pin)) {
          openPin(pin);
          return;
        }
        map.panTo([pin.longitude, pin.latitude]);
      });
      const more = document.createElement('button');
      more.type = 'button';
      more.className = 'more-dots';
      more.textContent = '⋯';
      more.setAttribute('aria-label', 'Още');
      more.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleListMenu(more, pin);
      });
      item.append(button, more);
      list.append(item);
    });
  if (!rows.length && period === 'today') {
    status.textContent = 'Няма събития за днес.';
  } else if (!nearbyRows.length) {
    status.textContent = `Няма нищо в радиус от ${kilometers} км.`;
  } else if (!rows.length) {
    status.textContent = 'Няма нищо в тази категория.';
  } else {
    status.textContent = '';
  }
}

document.getElementById('list-menu').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  const pin = listMenuPin;
  if (!button || !pin) {
    return;
  }
  if (button.dataset.act === 'copy') {
    button.dataset.label = 'Копирай';
    copyPinLink(pin, button);
    return;
  }
  closeListMenu();
  if (button.dataset.act === 'share') {
    sharePin(pin);
  } else if (button.dataset.act === 'report') {
    reportPin(pin);
  }
});

document.getElementById('nearby-list').addEventListener('scroll', closeListMenu);

document.addEventListener('click', (event) => {
  const menu = document.getElementById('list-menu');
  if (menu.hidden || menu.contains(event.target) || event.target.closest('.more-dots')) {
    return;
  }
  closeListMenu();
});

document.getElementById('category-filters').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  if (!button || !document.getElementById('category-filters').contains(button)) {
    return;
  }
  listCategory = button.dataset.category || '';
  document.querySelectorAll('#category-filters button').forEach((item) => {
    item.classList.toggle('on', item === button);
  });
  if (desktopMap()) {
    drawMarkers();
  }
  renderNearbyList();
});

function reloadForWindow() {
  pins = [];
  loadPins();
  if (!nearbyEl.hidden && listCenter) {
    loadNearby(Number(document.getElementById('radius').value));
  }
}

function paintDesktopTime() {
  document.querySelectorAll('#desktop-time button').forEach((button) => {
    const on = period !== 'today' && button.dataset.ahead === String(desktopAhead);
    button.classList.toggle('on', on);
    button.setAttribute('aria-pressed', on ? 'true' : 'false');
  });
}

document.getElementById('desktop-time').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  if (!button || !document.getElementById('desktop-time').contains(button)) {
    return;
  }
  period = '';
  desktopAhead = button.dataset.ahead;
  const url = new URL(window.location.href);
  url.searchParams.delete('period');
  if (desktopAhead === '10') {
    url.searchParams.delete('ahead');
  } else {
    url.searchParams.set('ahead', desktopAhead);
  }
  history.replaceState(null, '', url);
  paintDesktopTime();
  reloadForWindow();
});

function paintCityFilter() {
  document.querySelectorAll('#city-filters button').forEach((button) => {
    button.classList.toggle('on', (button.dataset.city || '') === listCity);
  });
}

function renderCityFilters() {
  const root = document.getElementById('city-filters');
  const all = root.querySelector('[data-city=""]');
  const buttons = CITIES.map((city) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.city = city.name;
    button.textContent = city.name;
    return button;
  });
  root.replaceChildren(all, ...buttons);
  paintCityFilter();
}

document.getElementById('city-filters').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  if (!button || !document.getElementById('city-filters').contains(button)) {
    return;
  }
  listCity = button.dataset.city || '';
  paintCityFilter();
  drawMarkers();
  renderNearbyList();
  if (!listCity) {
    return;
  }
  const city = CITIES.find((item) => item.name === listCity);
  if (!city) {
    return;
  }
  viewTouched = true;
  showCity(city.latitude, city.longitude);
});

document.getElementById('phone-time').addEventListener('click', (event) => {
  const button = event.target.closest('button');
  if (!button || !document.getElementById('phone-time').contains(button)) {
    return;
  }
  phoneAhead = button.dataset.ahead;
  const url = new URL(window.location.href);
  url.searchParams.delete('period');
  if (phoneAhead === '4') {
    url.searchParams.delete('ahead');
  } else {
    url.searchParams.set('ahead', phoneAhead);
  }
  history.replaceState(null, '', url);
  paintPhoneAhead();
  reloadForWindow();
});

function openNearby() {
  listCenter = map.getCenter();
  setAdminMenu(false);
  closePanel();
  closeUnassigned();
  document.getElementById('cities-modal').hidden = true;
  setPinsOpen(true);
  loadNearby(Number(document.getElementById('radius').value));
}

document.getElementById('open-pins').addEventListener('click', openNearby);
document.getElementById('open-list').addEventListener('click', openNearby);

document.getElementById('nearby-close').addEventListener('click', () => {
  setPinsOpen(false);
});

document.getElementById('filters-close').addEventListener('click', () => {
  setPinsOpen(false);
});

document.getElementById('radius').addEventListener('input', (event) => {
  document.getElementById('radius-label').innerHTML = `<b>${event.target.value} км</b>`;
});

document.getElementById('radius').addEventListener('change', (event) => {
  loadNearby(Number(event.target.value));
});

document.getElementById('locate').addEventListener('click', () => {
  if (!navigator.geolocation) {
    window.alert('Местоположението не е достъпно в този браузър.');
    return;
  }
  navigator.geolocation.getCurrentPosition(
    (position) => {
      forgetView();
      map.panTo([position.coords.longitude, position.coords.latitude]);
    },
    () => window.alert('Kostaa не успя да прочете къде си.'),
  );
});

banner.addEventListener('click', loadPins);

function setAdminMenu(open) {
  const dock = document.getElementById('admin-dock');
  if (!dock) {
    return;
  }
  document.getElementById('admin-cog').setAttribute('aria-expanded', open ? 'true' : 'false');
  const menu = document.getElementById('admin-menu');
  menu.setAttribute('aria-hidden', open ? 'false' : 'true');
  menu.inert = !open;
  document.getElementById('admin-scrim').hidden = !open;
  dock.classList.toggle('is-open', open);
}

const adminCog = document.getElementById('admin-cog');
if (adminCog) {
  adminCog.addEventListener('click', () => {
    setAdminMenu(!document.getElementById('admin-dock').classList.contains('is-open'));
  });
  document.getElementById('admin-scrim').addEventListener('click', () => setAdminMenu(false));
}

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeListMenu();
    setAdminMenu(false);
    closePanel();
    setPinsOpen(false);
    closeUnassigned();
    document.getElementById('cities-modal').hidden = true;
  }
});

const FEATURED = ['София', 'Пловдив', 'Варна', 'Бургас', 'Стара Загора'];
let cityCounts = new Map();

function paintCityButton(button, city) {
  button.replaceChildren();
  const name = document.createElement('span');
  name.textContent = city.name;
  button.append(name);
  if (cityCounts.has(city.name)) {
    const badge = document.createElement('span');
    badge.className = 'city-count';
    badge.textContent = `(${cityCounts.get(city.name)})`;
    button.append(badge);
  }
}

function cityButton(city, className) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = className;
  paintCityButton(button, city);
  button.addEventListener('click', () => goToCity(city));
  return button;
}

function renderCityChoices() {
  const list = document.getElementById('city-list');
  const needle = document.getElementById('city-search').value.trim().toLowerCase();
  const rows = CITIES.filter((city) => {
    if (!needle) {
      return true;
    }
    return city.name.toLowerCase().includes(needle) || (city.find || '').includes(needle);
  });
  list.replaceChildren(...rows.map((city) => cityButton(city, 'city-choice')));
  document.getElementById('city-empty').hidden = rows.length > 0;
}

const moreCities = document.getElementById('open-cities');
const pillButtons = [];
FEATURED.forEach((name) => {
  const city = CITIES.find((item) => item.name === name);
  const button = cityButton(city, 'city-pill');
  pillButtons.push({ button, city });
  moreCities.before(button);
});

function refreshCityButtons() {
  pillButtons.forEach(({ button, city }) => paintCityButton(button, city));
  if (!document.getElementById('cities-modal').hidden) {
    renderCityChoices();
  }
}

async function loadCityCounts() {
  try {
    const response = await fetch('/api/cities', { headers: headers() });
    if (!response.ok) {
      return;
    }
    const body = await response.json();
    cityCounts = new Map((body.cities || []).map((city) => [city.name, Number(city.count) || 0]));
    refreshCityButtons();
  } catch {
    // The city names still work without a number.
  }
}

function openCities() {
  setAdminMenu(false);
  closePanel();
  setPinsOpen(false);
  closeUnassigned();
  document.getElementById('city-search').value = '';
  renderCityChoices();
  document.getElementById('cities-modal').hidden = false;
  document.getElementById('city-search').focus();
  loadCityCounts();
}

loadCityCounts();

document.getElementById('open-cities').addEventListener('click', openCities);
document.getElementById('open-cities-mobile').addEventListener('click', openCities);

document.getElementById('city-search').addEventListener('input', renderCityChoices);

document.getElementById('cities-close').addEventListener('click', () => {
  document.getElementById('cities-modal').hidden = true;
});

const installEl = document.getElementById('install');
const installGo = document.getElementById('install-go');
const installHint = document.getElementById('install-hint');
const phoneScreen = window.matchMedia('(max-width: 800px)').matches;
const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
const ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
let installEvent = null;

function showInstall() {
  if (!phoneScreen || installed || localStorage.getItem('kostaa-install-dismissed') === '1') {
    return;
  }
  installHint.hidden = !ios;
  installGo.hidden = ios || !installEvent;
  installEl.hidden = false;
}

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  installEvent = event;
  showInstall();
});

installGo.addEventListener('click', async () => {
  if (!installEvent) {
    return;
  }
  installEvent.prompt();
  installEvent = null;
  installEl.hidden = true;
});

document.getElementById('install-close').addEventListener('click', () => {
  localStorage.setItem('kostaa-install-dismissed', '1');
  installEl.hidden = true;
});

if (phoneScreen && ios) {
  showInstall();
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}

map.on('moveend', () => {
  drawMarkers();
  scheduleLoad();
});

setInterval(() => {
  const now = Date.now();
  pins = pins.filter((pin) => new Date(pin.expires_at).getTime() > now);
  if (selected && new Date(selected.expires_at).getTime() <= now) {
    closePanel();
  }
  empty.hidden = pins.length !== 0 || !banner.hidden;
  drawMarkers();
  if (selected) {
    renderPanel();
  }
}, 30000);

const NEAR_KM = 2;
const FRESH_MS = 45000;
const toast = document.getElementById('toast');
let watchArmed = false;
let seenIds = new Set();
let toastPin = null;
let toastTimer = 0;
let geoState = 'idle';
let noteAsked = false;

function showToast(body, pin) {
  toastPin = pin;
  toast.replaceChildren();
  const title = document.createElement('b');
  title.textContent = 'Нов пин наблизо';
  const text = document.createElement('span');
  text.textContent = body;
  toast.append(title, text);
  toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.hidden = true;
  }, 8000);
  systemNote(body, pin);
}

async function systemNote(body, pin) {
  if (!('Notification' in window)) {
    return;
  }
  if (!noteAsked && Notification.permission === 'default') {
    noteAsked = true;
    try {
      await Notification.requestPermission();
    } catch {
      return;
    }
  }
  if (Notification.permission !== 'granted') {
    return;
  }
  const note = new Notification('Нов пин наблизо', { body });
  note.onclick = () => {
    window.focus();
    map.panTo([pin.longitude, pin.latitude]);
    note.close();
  };
}

toast.addEventListener('click', () => {
  if (toastPin) {
    map.panTo([toastPin.longitude, toastPin.latitude]);
  }
  toast.hidden = true;
});

async function scanNear(point) {
  const region = boxForRadius(point.lat, point.lng, NEAR_KM);
  const params = withPeriod(new URLSearchParams({
    north: String(region.latitude + region.latitudeDelta / 2),
    south: String(region.latitude - region.latitudeDelta / 2),
    east: String(region.longitude + region.longitudeDelta / 2),
    west: String(region.longitude - region.longitudeDelta / 2),
  }));
  const response = await fetch(`/api/pins?${params}`, { headers: headers() });
  if (!response.ok) {
    return;
  }
  const body = await response.json();
  const rows = (body.pins || []).filter((pin) => (
    kmBetween(point.lat, point.lng, pin.latitude, pin.longitude) <= NEAR_KM + 0.05
  ));
  if (!watchArmed) {
    rows.forEach((pin) => seenIds.add(pin.id));
    watchArmed = true;
    return;
  }
  const now = Date.now();
  const fresh = rows.filter((pin) => {
    if (pin.mine || seenIds.has(pin.id)) {
      return false;
    }
    const created = new Date(pin.created_at).getTime();
    return Number.isFinite(created) && now - created <= FRESH_MS;
  }).sort((a, b) => (
    kmBetween(point.lat, point.lng, a.latitude, a.longitude)
    - kmBetween(point.lat, point.lng, b.latitude, b.longitude)
  ));
  fresh.forEach((pin) => seenIds.add(pin.id));
  if (!fresh.length) {
    return;
  }
  const extra = fresh.length - 1;
  const text = extra > 0 ? `${fresh[0].message} и още ${extra}` : fresh[0].message;
  showToast(text, fresh[0]);
}

function watchNearby() {
  if (!navigator.geolocation || geoState === 'denied' || geoState === 'asking') {
    return;
  }
  geoState = 'asking';
  navigator.geolocation.getCurrentPosition(
    (position) => {
      geoState = 'granted';
      scanNear({ lat: position.coords.latitude, lng: position.coords.longitude }).catch(() => {});
    },
    (error) => {
      geoState = error && error.code === 1 ? 'denied' : 'idle';
    },
    { enableHighAccuracy: false, maximumAge: 20000, timeout: 8000 },
  );
}

const desktopBoot = window.matchMedia('(min-width: 801px)').matches;
let introPlayed = false;
let introToken = 0;

function hideBoot() {
  const boot = document.getElementById('boot');
  if (!boot || boot.hidden || boot.classList.contains('is-done')) {
    return;
  }
  boot.classList.add('is-done');
  window.setTimeout(() => {
    boot.hidden = true;
  }, 450);
}

function showCity(latitude, longitude, options = {}) {
  const span = options.span || 0.08;
  const frame = (size, duration) => frameMap(
    longitude - size / 2,
    latitude - size / 2,
    longitude + size / 2,
    latitude + size / 2,
    duration,
  );
  if (desktopBoot && !introPlayed) {
    introPlayed = true;
    const token = ++introToken;
    const zoomIn = () => {
      if (token !== introToken) {
        return;
      }
      introToken += 1;
      hideBoot();
      frame(span, 1100);
    };
    const arm = () => {
      frame(Math.min(0.13, span * 1.8), 0);
      map.once('moveend', zoomIn);
      window.setTimeout(zoomIn, 700);
    };
    if (map.isStyleLoaded()) {
      arm();
    } else {
      map.once('load', arm);
    }
    return;
  }
  introToken += 1;
  hideBoot();
  frame(span, 900);
}

let viewBeforePin = null;

function rememberView() {
  if (!desktopBoot || viewBeforePin) {
    return;
  }
  const center = map.getCenter();
  viewBeforePin = {
    center: [center.lng, center.lat],
    zoom: map.getZoom(),
    pitch: map.getPitch(),
    bearing: map.getBearing(),
    viewTouched,
  };
}

function forgetView() {
  viewBeforePin = null;
}

function restoreView() {
  if (!viewBeforePin) {
    return;
  }
  const previous = viewBeforePin;
  viewBeforePin = null;
  viewTouched = previous.viewTouched;
  map.setPadding({ top: 0, right: 0, bottom: 0, left: 0 });
  map.easeTo({
    center: previous.center,
    zoom: previous.zoom,
    pitch: previous.pitch,
    bearing: previous.bearing,
    duration: 700,
  });
}

function focusPin(pin, options = {}) {
  if (!desktopBoot) {
    return false;
  }
  const latitude = Number(pin.latitude);
  const longitude = Number(pin.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
    return false;
  }
  rememberView();
  viewTouched = true;
  introToken += 1;
  const span = 0.006;
  const bounds = map.getBounds();
  const padding = options.padPanel
    ? { top: 48, right: 440, bottom: 48, left: 48 }
    : { top: 48, right: 48, bottom: 48, left: 48 };
  if (bounds.getNorth() - bounds.getSouth() <= span) {
    map.easeTo({
      center: [longitude, latitude],
      duration: 700,
      padding,
    });
    return true;
  }
  frameMap(
    longitude - span / 2,
    latitude - span / 2,
    longitude + span / 2,
    latitude + span / 2,
    850,
    padding,
  );
  return true;
}

function goToCity(city) {
  viewTouched = true;
  forgetView();
  document.getElementById('cities-modal').hidden = true;
  setPinsOpen(false);
  closePanel();
  closeUnassigned();
  showCity(city.latitude, city.longitude);
}

let holdLink = /^\d+$/.test(new URLSearchParams(window.location.search).get('pin') || '');

function revealIfFar(point) {
  if (holdLink || !point) {
    return;
  }
  if (viewTouched && placed) {
    return;
  }
  if (placed) {
    const center = map.getCenter();
    if (kmBetween(center.lat, center.lng, point.lat, point.lng) <= 1) {
      return;
    }
  }
  showCity(point.lat, point.lng);
}

function cityPoint(latitude, longitude) {
  const lat = Number(latitude);
  const lng = Number(longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) {
    return null;
  }
  return { lat, lng };
}

async function lookupIpCity(url, read) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 2500);
  try {
    const response = await fetch(url, { signal: controller.signal });
    if (!response.ok) {
      return null;
    }
    return read(await response.json());
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

function followCity() {
  return new Promise((resolve) => {
    if (!navigator.geolocation || geoState === 'denied') {
      resolve(null);
      return;
    }
    geoState = 'asking';
    let settled = false;
    const watchId = navigator.geolocation.watchPosition(
      (position) => {
        geoState = 'granted';
        const point = cityPoint(position.coords.latitude, position.coords.longitude);
        if (!point) {
          return;
        }
        revealIfFar(point);
        if (!settled) {
          settled = true;
          resolve(point);
          scanNear(point).catch(() => {});
        }
        const accuracy = Number(position.coords.accuracy);
        if (Number.isFinite(accuracy) && accuracy > 0 && accuracy <= 300) {
          navigator.geolocation.clearWatch(watchId);
        }
      },
      (error) => {
        geoState = error && error.code === 1 ? 'denied' : 'idle';
        navigator.geolocation.clearWatch(watchId);
        if (!settled) {
          settled = true;
          resolve(null);
        }
      },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    );
    setTimeout(() => navigator.geolocation.clearWatch(watchId), 25000);
  });
}

function placeLinkedPin(pin) {
  const latitude = Number(pin.latitude);
  const longitude = Number(pin.longitude);
  const zoom = zoomForDelta(latitude, LINK_DELTA);
  placed = true;
  viewTouched = true;
  introToken += 1;
  const move = () => {
    map.setPadding({ top: 0, right: 0, bottom: 0, left: 0 });
    map.jumpTo({
      center: [longitude, latitude],
      zoom,
      pitch: Math.max(map.getPitch(), 48),
      bearing: map.getBearing(),
    });
    drawMarkers();
  };
  if (map.isStyleLoaded()) {
    move();
    return;
  }
  map.once('load', move);
}

async function openLinkedPin() {
  const id = new URLSearchParams(window.location.search).get('pin');
  if (!id || !/^\d+$/.test(id)) {
    return false;
  }
  try {
    const response = await fetch(`/api/pins/${id}`, { headers: headers() });
    if (!response.ok) {
      return false;
    }
    const body = await response.json();
    const pin = body.pin;
    if (!pin || !Number.isFinite(Number(pin.latitude)) || !Number.isFinite(Number(pin.longitude))) {
      return false;
    }
    pins = [pin, ...pins.filter((item) => item.id !== pin.id)];
    openPin(pin);
    placeLinkedPin(pin);
    hideBoot();
    return true;
  } catch {
    return false;
  }
}

function openTodayList(city) {
  listCenter = { lat: city.latitude, lng: city.longitude };
  const radius = document.getElementById('radius');
  radius.value = '18';
  document.getElementById('radius-label').innerHTML = '<b>18 км</b>';
  closePanel();
  document.getElementById('cities-modal').hidden = true;
  setPinsOpen(true);
  loadNearby(18);
}

async function openOnCity() {
  paintPhoneAhead();
  paintDesktopTime();
  if (holdLink) {
    const opened = await openLinkedPin();
    if (opened) {
      return;
    }
    holdLink = false;
  }

  const linked = cityBySlug(pageParams.get('city') || '');
  if (linked) {
    viewTouched = true;
    showCity(linked.latitude, linked.longitude, { span: period === 'today' ? 0.32 : 0.08 });
    if (period === 'today') {
      openTodayList(linked);
    }
    return;
  }

  const staraZagora = CITIES.find((city) => city.name === 'Стара Загора');
  showCity(staraZagora.latitude, staraZagora.longitude, { span: 0.022 });
}

setInterval(() => loadPins(), 20000);
setInterval(watchNearby, 20000);
placeFilterTools();
renderCityFilters();
openOnCity();
watchNearby();

function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

function adminHeaders() {
  return {
    ...headers(),
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrfToken(),
  };
}

const banButton = document.getElementById('ban-device');
if (banButton) {
  banButton.addEventListener('click', async () => {
    if (!selected || !window.confirm('Забраняваш ли това устройство? Активните му пинове изчезват от картата и то не може да пуска нови.')) {
      return;
    }
    try {
      const response = await fetch(`/admin/pins/${selected.id}/ban`, {
        method: 'POST',
        headers: adminHeaders(),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const body = await response.json();
      const hidden = new Set(body.hidden_pin_ids || []);
      pins = pins.filter((pin) => !hidden.has(pin.id));
      empty.hidden = pins.length !== 0;
      closePanel();
      drawMarkers();
    } catch (err) {
      window.alert(err.message || 'Устройството не можа да бъде забранено.');
    }
  });
}

const relocateBox = document.getElementById('relocate');
const openRelocate = document.getElementById('open-relocate');
if (relocateBox && openRelocate) {
  const relocateAddress = document.getElementById('relocate-address');
  const relocateError = document.getElementById('relocate-error');
  const relocatePick = document.getElementById('relocate-pick');
  let relocateLock = false;
  let addressLanded = false;

  function showRelocateError(message) {
    relocateError.hidden = false;
    relocateError.textContent = message;
  }

  function flyToMovedPin(moved) {
    const latitude = Number(moved.latitude);
    const longitude = Number(moved.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      return;
    }
    viewTouched = true;
    forgetView();
    introToken += 1;
    const desktop = window.matchMedia('(min-width: 801px)').matches;
    let padding;
    if (desktop) {
      const edges = [panel.getBoundingClientRect().left];
      if (!relocateBox.hidden) {
        edges.push(relocateBox.getBoundingClientRect().left);
      }
      const leftEdge = Math.min(...edges.filter((value) => value > 0));
      const covered = Number.isFinite(leftEdge) ? window.innerWidth - leftEdge : 440;
      padding = {
        top: 80,
        right: Math.min(Math.round(covered + 20), window.innerWidth - 160),
        bottom: 48,
        left: 48,
      };
    } else {
      padding = {
        top: 72,
        right: 16,
        bottom: Math.round((relocateBox.hidden ? 0 : relocateBox.getBoundingClientRect().height) + 28),
        left: 16,
      };
    }
    map.easeTo({
      center: [longitude, latitude],
      zoom: 16,
      padding,
      pitch: Math.max(map.getPitch(), 48),
      bearing: map.getBearing(),
      duration: 1100,
    });
  }

  async function moveSelected(body) {
    if (!selected || relocateLock) {
      return;
    }
    relocateLock = true;
    relocateError.hidden = true;
    try {
      const response = await fetch(`/admin/pins/${selected.id}/move`, {
        method: 'POST',
        headers: { ...adminHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const moved = await response.json();
      pins = [moved, ...pins.filter((pin) => pin.id !== moved.id)];
      selected = moved;
      empty.hidden = pins.length !== 0;
      drawMarkers();
      if (body.address != null) {
        if (!window.matchMedia('(min-width: 801px)').matches) {
          panel.hidden = true;
        }
        renderPanel();
        addressLanded = true;
        flyToMovedPin(moved);
        return;
      }
      addressLanded = false;
      closeRelocate();
      panel.hidden = false;
      renderPanel();
      flyToMovedPin(moved);
    } catch (err) {
      showRelocateError(err.message || 'Мястото не е записано.');
      if (selected) {
        panel.hidden = false;
      }
    } finally {
      relocateLock = false;
    }
  }

  openRelocate.addEventListener('click', () => {
    if (!selected) {
      return;
    }
    stopPicking();
    placingId = null;
    relocateAddress.value = '';
    relocateError.hidden = true;
    addressLanded = false;
    relocateBox.hidden = false;
  });

  document.getElementById('relocate-close').addEventListener('click', () => {
    const pin = selected;
    const landed = addressLanded;
    addressLanded = false;
    closeRelocate();
    if (!pin) {
      return;
    }
    panel.hidden = false;
    if (landed) {
      flyToMovedPin(pin);
    }
  });

  document.getElementById('relocate-form').addEventListener('submit', (event) => {
    event.preventDefault();
    const address = relocateAddress.value.trim();
    if (!address) {
      showRelocateError('Напиши адрес или избери място на картата.');
      return;
    }
    stopRelocatePick();
    moveSelected({ address });
  });

  relocatePick.addEventListener('click', () => {
    if (!selected) {
      return;
    }
    stopPicking();
    placingId = null;
    relocating = true;
    map.getCanvas().style.cursor = 'crosshair';
    relocatePick.textContent = 'Кликни върху картата';
    relocateError.hidden = true;
    if (!window.matchMedia('(min-width: 801px)').matches) {
      panel.hidden = true;
    }
  });

  map.on('click', (event) => {
    if (!relocating || !selected || relocateLock) {
      return;
    }
    moveSelected({
      latitude: event.lngLat.lat,
      longitude: event.lngLat.lng,
    });
  });
}

function stopPicking() {
  picking = false;
  map.getCanvas().style.cursor = '';
  const button = document.getElementById('pick-spot');
  if (button) {
    button.textContent = 'Избери на картата';
  }
}

function closeUnassigned() {
  placingId = null;
  const box = document.getElementById('unassigned');
  if (box) {
    box.hidden = true;
  }
  if (!picking) {
    map.getCanvas().style.cursor = '';
  }
}

function chooseFilter(box, input, key) {
  box.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button || !button.dataset[key]) {
      return;
    }
    input.value = button.dataset[key];
    box.querySelectorAll('button').forEach((item) => {
      item.classList.toggle('on', item === button);
    });
  });
}

const composer = document.getElementById('composer');
const openComposer = document.getElementById('open-composer');
if (composer && openComposer) {
  const pinMessage = document.getElementById('pin-message');
  const pinError = document.getElementById('pin-error');

  openComposer.addEventListener('click', () => {
    closeRelocate();
    panel.hidden = true;
    setPinsOpen(false);
    const cities = document.getElementById('cities-modal');
    if (cities) {
      cities.hidden = true;
    }
    composer.hidden = false;
    closeUnassigned();
  });

  document.getElementById('composer-close').addEventListener('click', () => {
    composer.hidden = true;
    stopPicking();
  });

  pinMessage.addEventListener('input', () => {
    document.getElementById('pin-count').textContent = `${pinMessage.value.trim().length}/80`;
  });

  chooseFilter(
    document.getElementById('composer-categories'),
    document.getElementById('pin-category'),
    'category',
  );
  chooseFilter(
    document.getElementById('composer-durations'),
    document.getElementById('pin-duration'),
    'duration',
  );
  chooseFilter(
    document.getElementById('composer-when'),
    document.getElementById('pin-kind'),
    'when',
  );
  document.getElementById('composer-when').addEventListener('click', () => {
    const upcoming = document.getElementById('pin-kind').value === 'upcoming';
    document.getElementById('pin-range').hidden = !upcoming;
    document.getElementById('composer-durations').hidden = upcoming;
  });

  document.getElementById('pick-spot').addEventListener('click', () => {
    closeRelocate();
    placingId = null;
    picking = true;
    map.getCanvas().style.cursor = 'crosshair';
    document.getElementById('pick-spot').textContent = 'Кликни върху картата';
  });

  map.on('click', (event) => {
    if (!picking) {
      return;
    }
    const latitude = event.lngLat.lat;
    const longitude = event.lngLat.lng;
    document.getElementById('pin-lat').value = latitude.toFixed(6);
    document.getElementById('pin-lng').value = longitude.toFixed(6);
    document.getElementById('pin-spot').textContent = `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
    if (draftMarker) {
      draftMarker.remove();
    }
    draftMarker = new maplibregl.Marker({ color: '#c2410c' })
      .setLngLat([longitude, latitude])
      .addTo(map);
    stopPicking();
  });

  document.getElementById('admin-pin-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = pinMessage.value.trim();
    const latitude = document.getElementById('pin-lat').value;
    const longitude = document.getElementById('pin-lng').value;
    const kind = document.getElementById('pin-kind').value || 'normal';
    const startsAt = document.getElementById('pin-start').value;
    const endsAt = document.getElementById('pin-end').value;
    if (!message) {
      pinError.hidden = false;
      pinError.textContent = 'Напиши кратко съобщение.';
      return;
    }
    if (kind === 'upcoming' && (!startsAt || !endsAt)) {
      pinError.hidden = false;
      pinError.textContent = 'Избери от кога до кога.';
      return;
    }
    if (!latitude || !longitude) {
      pinError.hidden = false;
      pinError.textContent = 'Избери място на картата.';
      return;
    }
    const send = document.getElementById('pin-send');
    send.disabled = true;
    try {
      const payload = {
        message,
        latitude: Number(latitude),
        longitude: Number(longitude),
        category: document.getElementById('pin-category').value,
        kind,
      };
      if (kind === 'upcoming') {
        payload.starts_at = startsAt;
        payload.ends_at = endsAt;
      } else {
        payload.duration = document.getElementById('pin-duration').value;
      }
      const response = await fetch('/admin/pins', {
        method: 'POST',
        headers: { ...adminHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const created = await response.json();
      pins = [created, ...pins.filter((item) => item.id !== created.id)];
      empty.hidden = true;
      drawMarkers();
      if (draftMarker) {
        draftMarker.remove();
        draftMarker = null;
      }
      pinMessage.value = '';
      document.getElementById('pin-count').textContent = '0/80';
      document.getElementById('pin-lat').value = '';
      document.getElementById('pin-lng').value = '';
      document.getElementById('pin-start').value = '';
      document.getElementById('pin-end').value = '';
      document.getElementById('pin-spot').textContent = 'Няма избрано място.';
      pinError.hidden = true;
      composer.hidden = true;
      stopPicking();
    } catch (err) {
      pinError.hidden = false;
      pinError.textContent = err.message || 'Пинът не е пуснат.';
    } finally {
      send.disabled = false;
    }
  });
}

const unassignedEl = document.getElementById('unassigned');
const openUnassigned = document.getElementById('open-unassigned');
if (unassignedEl && openUnassigned) {
  const unassignedError = document.getElementById('unassigned-error');
  let placingLock = false;

  function renderUnassigned() {
    const list = document.getElementById('unassigned-list');
    const hint = document.getElementById('unassigned-hint');
    list.replaceChildren();
    hint.textContent = placingId
      ? 'Кликни върху картата, за да поставиш пина.'
      : 'Избери събитие, после кликни върху картата.';
    if (!unassignedRows.length) {
      const emptyRow = document.createElement('li');
      emptyRow.className = 'faint';
      emptyRow.textContent = 'Няма събития без място.';
      list.append(emptyRow);
      return;
    }
    unassignedRows.forEach((pin) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      if (placingId === pin.id) {
        button.className = 'on';
      }
      const message = document.createElement('strong');
      message.textContent = pin.message;
      const meta = document.createElement('small');
      meta.textContent = [
        categoryLabel(pin.category),
        pin.starts_at ? formatWhen(pin.starts_at) : '',
        durationText(pin.duration_minutes),
      ].filter(Boolean).join(' · ');
      button.append(message, meta);
      button.addEventListener('click', () => {
        closeRelocate();
        placingId = pin.id;
        stopPicking();
        map.getCanvas().style.cursor = 'crosshair';
        renderUnassigned();
      });
      item.append(button);
      list.append(item);
    });
  }

  async function loadUnassigned() {
    const response = await fetch('/admin/pins/unassigned', { headers: adminHeaders() });
    if (response.status === 401 || response.status === 419) {
      window.alert('Влез пак като администратор.');
      return;
    }
    if (!response.ok) {
      throw new Error(await readError(response));
    }
    const body = await response.json();
    unassignedRows = body.pins || [];
    renderUnassigned();
  }

  openUnassigned.addEventListener('click', () => {
    closeRelocate();
    panel.hidden = true;
    setPinsOpen(false);
    document.getElementById('cities-modal').hidden = true;
    if (composer) {
      composer.hidden = true;
      stopPicking();
    }
    unassignedEl.hidden = false;
    loadUnassigned().catch((err) => {
      unassignedError.hidden = false;
      unassignedError.textContent = err.message || 'Списъкът не се зареди.';
    });
  });

  document.getElementById('unassigned-close').addEventListener('click', () => {
    closeUnassigned();
  });

  map.on('click', async (event) => {
    if (!placingId || picking || placingLock) {
      return;
    }
    const id = placingId;
    placingLock = true;
    try {
      const response = await fetch(`/admin/pins/${id}/place`, {
        method: 'POST',
        headers: { ...adminHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({
          latitude: event.lngLat.lat,
          longitude: event.lngLat.lng,
        }),
      });
      if (response.status === 401 || response.status === 419) {
        window.alert('Влез пак като администратор.');
        return;
      }
      if (!response.ok) {
        throw new Error(await readError(response));
      }
      const placed = await response.json();
      unassignedRows = unassignedRows.filter((pin) => pin.id !== id);
      pins = [placed, ...pins.filter((item) => item.id !== placed.id)];
      empty.hidden = pins.length !== 0;
      placingId = null;
      map.getCanvas().style.cursor = '';
      unassignedError.hidden = true;
      renderUnassigned();
      drawMarkers();
    } catch (err) {
      unassignedError.hidden = false;
      unassignedError.textContent = err.message || 'Мястото не е записано.';
    } finally {
      placingLock = false;
    }
  });
}
