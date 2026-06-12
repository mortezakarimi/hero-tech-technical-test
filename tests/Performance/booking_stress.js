/**
 * k6 booking stress test
 *
 * Run:
 *   k6 run tests/Performance/booking_stress.js -e BASE_URL=http://localhost
 *
 * Optional env:
 *   EVENT_COUNT=5 HOT_EVENT_CAPACITY=10 CONCURRENT_VUS=30 SETUP_EVENT_CAPACITY=50
 *   ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD=password
 *
 * Note: register/login are throttled (6/min). Staggered scenarios reduce 429s.
 * For heavy registration load, temporarily raise throttle limits in routes/api.php.
 */
import http from 'k6/http';
import { check, group, sleep, } from 'k6';
import { Counter, Rate } from 'k6/metrics';
import exec from 'k6/execution';
import { randomItem } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const ADMIN_EMAIL = __ENV.ADMIN_EMAIL || 'admin@example.com';
const ADMIN_PASSWORD = __ENV.ADMIN_PASSWORD || 'password';
let cachedSetup = null;
const EVENT_COUNT = Number(__ENV.EVENT_COUNT || 5);
const HOT_EVENT_CAPACITY = Number(__ENV.HOT_EVENT_CAPACITY || 10);
const SETUP_EVENT_CAPACITY = Number(__ENV.SETUP_EVENT_CAPACITY || 50);

const registrations = new Counter('registrations');
const registrationFailures = new Counter('registration_failures');
const bookingsCreated = new Counter('bookings_created');
const bookingsRejected = new Counter('bookings_rejected');
const paymentsSucceeded = new Counter('payments_succeeded');
const paymentsFailed = new Counter('payments_failed');
const lifecycleSuccess = new Counter('lifecycle_success');
const lifecycleFailure = new Counter('lifecycle_failure');
const hotEventBookingSuccess = new Counter('hot_event_booking_success');
const oversellDetected = new Rate('oversell_detected');

export const options = {
    setupTimeout: '120s',
    scenarios: {
        read_heavy: {
            executor: 'constant-vus',
            exec: 'readEvents',
            vus: 15,
            duration: '45s',
        },
        concurrent_hot_event: {
            executor: 'shared-iterations',
            exec: 'concurrentHotEventBooking',
            vus: Number(__ENV.CONCURRENT_VUS || 30),
            iterations: Number(__ENV.CONCURRENT_VUS || 30),
            maxDuration: '3m',
            startTime: '5s',
        },
        lifecycle_success: {
            executor: 'per-vu-iterations',
            exec: 'bookingWithSuccessfulPayment',
            vus: 8,
            iterations: 2,
            maxDuration: '3m',
            startTime: '10s',
        },
        lifecycle_failure: {
            executor: 'per-vu-iterations',
            exec: 'bookingWithFailedPayment',
            vus: 8,
            iterations: 2,
            maxDuration: '3m',
            startTime: '15s',
        },
        multi_event_pending: {
            executor: 'ramping-vus',
            exec: 'bookRandomEventPending',
            startVUs: 0,
            stages: [
                { duration: '10s', target: 20 },
                { duration: '20s', target: 20 },
                { duration: '5s', target: 0 },
            ],
            startTime: '8s',
        },
    },
    thresholds: {
        oversell_detected: ['rate==0'],
        registrations: ['count>0'],
        bookings_created: ['count>0'],
        http_req_failed: ['rate<0.6'],
    },
};

function jsonHeaders(token = null) {
    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return { headers };
}

function formatDateTime(date) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

function futureEventWindow(daysAhead, durationHours = 2) {
    const start = new Date(Date.now() + daysAhead * 86400000);
    const end = new Date(start.getTime() + durationHours * 3600000);

    return { start, end };
}

function loginAdmin() {
    const response = http.post(
        `${BASE_URL}/api/v1/login`,
        JSON.stringify({
            email: ADMIN_EMAIL,
            password: ADMIN_PASSWORD,
            device_name: 'k6-setup',
        }),
        jsonHeaders(),
    );

    if (response.status !== 200) {
        return null;
    }

    return JSON.parse(response.body).token;
}

function createEvent(title, capacity, daysAhead, token) {
    const { start, end } = futureEventWindow(daysAhead);

    const response = http.post(
        `${BASE_URL}/api/v1/events`,
        JSON.stringify({
            title,
            description: 'k6 stress test event',
            total_capacity: capacity,
            start_time: formatDateTime(start),
            end_time: formatDateTime(end),
        }),
        jsonHeaders(token),
    );

    if (response.status !== 201) {
        return null;
    }

    const data = JSON.parse(response.body).data;

    return {
        id: data.id,
        title: data.title,
        capacity: data.total_capacity,
    };
}

function registerUser(label) {
    const uniqueSuffix = `${label}-${exec.vu.idInInstance}-${exec.scenario.iterationInTest}-${Date.now()}`;
    const email = `k6-${uniqueSuffix}@perf.test`;
    const payload = JSON.stringify({
        name: `K6 User ${uniqueSuffix}`,
        email,
        password: 'password123',
        password_confirmation: 'password123',
        device_name: 'k6-stress',
    });

    for (let attempt = 0; attempt < 3; attempt++) {
        const response = http.post(`${BASE_URL}/api/v1/register`, payload, jsonHeaders());

        if (response.status === 201) {
            registrations.add(1);

            return {
                token: JSON.parse(response.body).token,
                email,
            };
        }

        if (response.status === 429) {
            sleep(10 + attempt * 5);
            continue;
        }

        break;
    }

    registrationFailures.add(1);

    return null;
}

function createBooking(token, eventId) {
    return http.post(
        `${BASE_URL}/api/v1/events/${eventId}/bookings`,
        null,
        jsonHeaders(token),
    );
}

function processPayment(token, bookingId, outcome) {
    return http.post(
        `${BASE_URL}/api/v1/bookings/${bookingId}/payment`,
        JSON.stringify({ simulate: outcome }),
        jsonHeaders(token),
    );
}

export function setup() {
    const adminToken = loginAdmin();

    if (! adminToken) {
        throw new Error('Failed to login as admin for event setup. Seed admin@example.com or set ADMIN_EMAIL/ADMIN_PASSWORD.');
    }

    const events = [];

    for (let index = 0; index < EVENT_COUNT; index++) {
        const event = createEvent(`K6 Pool Event ${index + 1}`, SETUP_EVENT_CAPACITY, index + 2, adminToken);

        if (event) {
            events.push(event);
        }

        sleep(0.05);
    }

    const hotEvent = createEvent('K6 Hot Event (Concurrent)', HOT_EVENT_CAPACITY, 1, adminToken);

    if (! hotEvent) {
        throw new Error('Failed to create hot event for concurrent booking scenario');
    }

    cachedSetup = {
        baseUrl: BASE_URL,
        events,
        hotEvent,
    };

    return cachedSetup;
}

export function readEvents(data) {
    const response = http.get(`${data.baseUrl}/api/v1/events?filter[has_spots]=true&per_page=50`);

    check(response, {
        'events list returns 200': (r) => r.status === 200,
        'events list has data': (r) => Array.isArray(JSON.parse(r.body).data),
    });

    sleep(0.1);
}

export function concurrentHotEventBooking(data) {
    group('concurrent hot event booking', () => {
        const user = registerUser('hot');

        if (! user) {
            return;
        }

        const response = createBooking(user.token, data.hotEvent.id);

        if (response.status === 201) {
            bookingsCreated.add(1);
            hotEventBookingSuccess.add(1);
        } else if ([409, 422].includes(response.status)) {
            bookingsRejected.add(1);
        }

        check(response, {
            'concurrent booking response is valid': (r) => [201, 409, 422].includes(r.status),
        });
    });

    sleep(0.05);
}

export function bookingWithSuccessfulPayment(data) {
    group('register → book → pay success', () => {
        const user = registerUser('success');

        if (! user || data.events.length === 0) {
            return;
        }

        const event = randomItem(data.events);
        const bookResponse = createBooking(user.token, event.id);

        check(bookResponse, {
            'booking created for payment success flow': (r) => r.status === 201,
        });

        if (bookResponse.status !== 201) {
            if ([409, 422].includes(bookResponse.status)) {
                bookingsRejected.add(1);
            }

            return;
        }

        bookingsCreated.add(1);

        const booking = JSON.parse(bookResponse.body).data;
        const payResponse = processPayment(user.token, booking.id, 'success');

        check(payResponse, {
            'payment succeeds and confirms booking': (r) => r.status === 200,
            'payment status is succeeded': (r) => JSON.parse(r.body).data.status === 'succeeded',
            'booking listable after confirmation': (r) => {
                if (r.status !== 200) {
                    return false;
                }

                const showResponse = http.get(
                    `${data.baseUrl}/api/v1/bookings/${booking.id}`,
                    jsonHeaders(user.token),
                );

                return showResponse.status === 200
                    && JSON.parse(showResponse.body).data.status === 'confirmed';
            },
        });

        if (payResponse.status === 200) {
            paymentsSucceeded.add(1);
            lifecycleSuccess.add(1);
        }
    });

    sleep(0.15);
}

export function bookingWithFailedPayment(data) {
    group('register → book → pay failure', () => {
        const user = registerUser('fail');

        if (! user || data.events.length === 0) {
            return;
        }

        const event = randomItem(data.events);
        const bookResponse = createBooking(user.token, event.id);

        check(bookResponse, {
            'booking created for payment failure flow': (r) => r.status === 201,
        });

        if (bookResponse.status !== 201) {
            if ([409, 422].includes(bookResponse.status)) {
                bookingsRejected.add(1);
            }

            return;
        }

        bookingsCreated.add(1);

        const booking = JSON.parse(bookResponse.body).data;
        const payResponse = processPayment(user.token, booking.id, 'failure');

        check(payResponse, {
            'payment failure is processed': (r) => r.status === 200,
            'payment status is failed': (r) => JSON.parse(r.body).data.status === 'failed',
            'booking cancelled after failed payment': (r) => {
                if (r.status !== 200) {
                    return false;
                }

                const showResponse = http.get(
                    `${data.baseUrl}/api/v1/bookings/${booking.id}`,
                    jsonHeaders(user.token),
                );

                return showResponse.status === 200
                    && JSON.parse(showResponse.body).data.status === 'cancelled';
            },
        });

        if (payResponse.status === 200) {
            paymentsFailed.add(1);
            lifecycleFailure.add(1);
        }
    });

    sleep(0.15);
}

export function bookRandomEventPending(data) {
    group('register → book pending', () => {
        const user = registerUser('pending');

        if (! user || data.events.length === 0) {
            return;
        }

        const event = randomItem(data.events);
        const response = createBooking(user.token, event.id);

        if (response.status === 201) {
            bookingsCreated.add(1);

            const booking = JSON.parse(response.body).data;

            check(response, {
                'pending booking created': (r) => r.status === 201,
                'booking status is pending': () => booking.status === 'pending',
                'payment initiated on booking': () => booking.payment !== undefined
                    && booking.payment.status === 'pending',
            });
        } else if ([409, 422].includes(response.status)) {
            bookingsRejected.add(1);

            check(response, {
                'booking rejection is expected under contention': (r) => [409, 422].includes(r.status),
            });
        }
    });

    sleep(0.1);
}

function validateCapacityInvariants(baseUrl, hotEventId) {
    const response = http.get(`${baseUrl}/api/v1/events?per_page=100`);

    if (response.status !== 200) {
        return {
            oversold: true,
            error: 'Failed to fetch events for validation',
            events: [],
            hotEvent: null,
        };
    }

    const events = JSON.parse(response.body).data;
    const report = [];
    let oversold = false;

    for (const event of events) {
        const confirmedBookings = event.total_capacity - event.available_slots;
        const eventOversold = confirmedBookings > event.total_capacity;

        if (eventOversold) {
            oversold = true;
        }

        report.push({
            id: event.id,
            title: event.title,
            total_capacity: event.total_capacity,
            available_slots: event.available_slots,
            confirmed_bookings: confirmedBookings,
            oversold: eventOversold,
        });
    }

    const hotEventReport = hotEventId
        ? report.find((event) => event.id === hotEventId)
        : report.find((event) => event.title.includes('K6 Hot Event'));

    return {
        oversold,
        hotEvent: hotEventReport ?? null,
        events: report,
    };
}

export function handleSummary(data) {
    const hotEvent = cachedSetup?.hotEvent ?? { id: null, capacity: HOT_EVENT_CAPACITY };
    const validation = validateCapacityInvariants(BASE_URL, hotEvent.id);

    if (validation.oversold) {
        oversellDetected.add(true);
    }

    const hotSuccesses = data.metrics.hot_event_booking_success?.values?.count ?? 0;

    const hotCapacity = hotEvent.capacity ?? HOT_EVENT_CAPACITY;

    const summary = {
        capacity_validation: validation,
        hot_event: {
            id: hotEvent.id,
            capacity: hotCapacity,
            booking_attempts_succeeded: hotSuccesses,
            within_capacity: hotSuccesses <= hotCapacity,
        },
        metrics: {
            registrations: data.metrics.registrations?.values?.count ?? 0,
            registration_failures: data.metrics.registration_failures?.values?.count ?? 0,
            bookings_created: data.metrics.bookings_created?.values?.count ?? 0,
            bookings_rejected: data.metrics.bookings_rejected?.values?.count ?? 0,
            payments_succeeded: data.metrics.payments_succeeded?.values?.count ?? 0,
            payments_failed: data.metrics.payments_failed?.values?.count ?? 0,
            lifecycle_success: data.metrics.lifecycle_success?.values?.count ?? 0,
            lifecycle_failure: data.metrics.lifecycle_failure?.values?.count ?? 0,
        },
        thresholds_passed: ! validation.oversold && hotSuccesses <= hotCapacity,
    };

    return {
        stdout: JSON.stringify(summary, null, 2),
    };
}
