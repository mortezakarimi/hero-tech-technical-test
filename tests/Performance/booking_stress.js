import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const EVENT_ID = __ENV.EVENT_ID || '1';
const AUTH_TOKEN = __ENV.AUTH_TOKEN || '';

const bookingSuccess = new Counter('booking_success');
const bookingRejected = new Counter('booking_rejected');
const oversellDetected = new Rate('oversell_detected');

export const options = {
    scenarios: {
        read_heavy: {
            executor: 'constant-vus',
            exec: 'readEvents',
            vus: 20,
            duration: '30s',
        },
        booking_burst: {
            executor: 'ramping-vus',
            exec: 'createBooking',
            startVUs: 0,
            stages: [
                { duration: '5s', target: 50 },
                { duration: '10s', target: 50 },
                { duration: '5s', target: 0 },
            ],
            startTime: '5s',
        },
    },
    thresholds: {
        oversell_detected: ['rate==0'],
        http_req_failed: ['rate<0.5'],
    },
};

export function readEvents() {
    const response = http.get(`${BASE_URL}/api/v1/events?filter[has_spots]=true`);

    check(response, {
        'events list status is 200': (r) => r.status === 200,
    });

    sleep(0.1);
}

export function createBooking() {
    if (! AUTH_TOKEN) {
        return;
    }

    const response = http.post(
        `${BASE_URL}/api/v1/events/${EVENT_ID}/bookings`,
        null,
        {
            headers: {
                Authorization: `Bearer ${AUTH_TOKEN}`,
                Accept: 'application/json',
            },
        },
    );

    if (response.status === 201) {
        bookingSuccess.add(1);
    } else if ([409, 422].includes(response.status)) {
        bookingRejected.add(1);
    }

    check(response, {
        'booking response is valid': (r) => [201, 409, 422].includes(r.status),
    });

    sleep(0.05);
}

export function handleSummary(data) {
    const eventResponse = http.get(`${BASE_URL}/api/v1/events/${EVENT_ID}`);
    let capacity = 0;
    let available = 0;

    if (eventResponse.status === 200) {
        const body = JSON.parse(eventResponse.body);
        capacity = body.data.total_capacity;
        available = body.data.available_slots;
    }

    const activeBookings = capacity - available;
    const oversold = activeBookings > capacity;

    oversellDetected.add(oversold);

    return {
        stdout: JSON.stringify({
            capacity,
            available_slots: available,
            confirmed_bookings: activeBookings,
            oversold,
            booking_success: data.metrics.booking_success?.values?.count ?? 0,
            booking_rejected: data.metrics.booking_rejected?.values?.count ?? 0,
        }, null, 2),
    };
}
