import { postJson } from '@/lib/http';

type AuthParams = { socketId: string; channelName: string };
type AuthData = { auth: string; channel_data?: string };
type AuthCallback = (error: Error | null, data: AuthData | null) => void;

/**
 * Hosts and players are guests identified by cookies scoped to /host and
 * /play, so channel authorization has to go to the matching prefix for the
 * browser to send the right cookie.
 */
function authEndpoint(): string {
    const path = window.location.pathname;

    if (path.startsWith('/host')) {
        return '/host/broadcasting/auth';
    }

    if (path.startsWith('/play')) {
        return '/play/broadcasting/auth';
    }

    return '/broadcasting/auth';
}

export const channelAuthorization = {
    endpoint: '/broadcasting/auth',
    transport: 'ajax' as const,
    customHandler: (
        { socketId, channelName }: AuthParams,
        callback: AuthCallback,
    ) => {
        postJson(authEndpoint(), {
            socket_id: socketId,
            channel_name: channelName,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(
                        `Channel authorization failed with HTTP ${response.status}.`,
                    );
                }

                callback(null, (await response.json()) as AuthData);
            })
            .catch((error: Error) => callback(error, null));
    },
};
