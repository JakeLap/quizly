/** Laravel's CSRF token, from the XSRF-TOKEN cookie. */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** A small same-origin JSON POST, for requests that aren't page visits. */
export function postJson(url: string, body: unknown = {}): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
}

/**
 * Visit options for actions inside a room (start, skip, play again…): keep
 * the page instead of rebuilding it. Rebuilding the page briefly leaves the
 * room's live channel, so everyone else would see this screen or phone
 * disconnect and come back.
 */
export const stayInRoom = {
    preserveScroll: true,
    preserveState: true,
} as const;
