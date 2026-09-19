<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    private function getClient()
    {
        $client = Http::withoutVerifying()->timeout(10);
        $user = config('services.gowa.user');
        $password = config('services.gowa.password');

        if (! empty($user) && ! empty($password)) {
            $client = $client->withBasicAuth($user, $password);
        }

        return $client;
    }

    public function index(): View
    {
        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $devices = [];
        $authError = null;

        try {
            $response = $this->getClient()->get(rtrim($baseUrl, '/').'/devices');
            if ($response->status() === 401) {
                $authError = 'Autentikasi GoWA diperlukan. Pastikan GOWA_USER dan GOWA_PASSWORD sudah diatur di .env.';
            } elseif ($response->successful()) {
                $data = $response->json();
                if (isset($data['results']) && is_array($data['results'])) {
                    $devices = array_is_list($data['results']) ? $data['results'] : [$data['results']];
                }
            }
        } catch (\Throwable) {
            // Silently fall back to empty list, user can retry
        }

        return view('dashboard.whatsapp', [
            'gowaUrl' => $baseUrl,
            'devices' => $devices,
            'authError' => $authError,
            'hasAuth' => ! empty(config('services.gowa.user')) && ! empty(config('services.gowa.password')),
        ]);
    }

    public function devices(): JsonResponse
    {
        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');

        try {
            $response = $this->getClient()->get(rtrim($baseUrl, '/').'/devices');
            if ($response->status() === 401) {
                return response()->json(['status' => 'unauthorized', 'devices' => []], 401);
            }

            $data = $response->json();
            $devices = [];
            if (isset($data['results']) && is_array($data['results'])) {
                $devices = array_is_list($data['results']) ? $data['results'] : [$data['results']];
            }

            return response()->json([
                'status' => 'success',
                'devices' => $devices,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'devices' => [],
            ], 500);
        }
    }

    private function resolveOrCreateDeviceId(?string $requestedDeviceId = null): ?string
    {
        if (! empty($requestedDeviceId)) {
            return $requestedDeviceId;
        }

        $envDeviceId = config('services.gowa.device_id');
        if (! empty($envDeviceId)) {
            return $envDeviceId;
        }

        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $client = $this->getClient();

        try {
            $devicesRes = $client->get(rtrim($baseUrl, '/').'/devices');
            if ($devicesRes->successful()) {
                $devData = $devicesRes->json('results');
                if (! empty($devData)) {
                    $devList = array_is_list($devData) ? $devData : [$devData];
                    // Prefer a device that is not yet connected
                    foreach ($devList as $d) {
                        if (($d['state'] ?? '') !== 'connected') {
                            return $d['id'] ?? null;
                        }
                    }

                    // If all are connected, use the first device slot
                    if (! empty($devList[0]['id'])) {
                        return $devList[0]['id'];
                    }
                }
            }

            // If no device slot exists in GoWA, create one
            $createRes = $client->asJson()->post(rtrim($baseUrl, '/').'/devices', new \stdClass);
            if ($createRes->successful()) {
                return $createRes->json('results.id');
            }
        } catch (\Throwable) {
            // Silently return null
        }

        return null;
    }

    public function qr(Request $request): JsonResponse
    {
        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $deviceId = $this->resolveOrCreateDeviceId($request->query('device_id'));

        if (empty($deviceId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menemukan atau membuat slot perangkat WhatsApp di GoWA.',
            ], 500);
        }

        $endpoint = rtrim($baseUrl, '/').'/devices/'.urlencode($deviceId).'/login';

        try {
            $client = $this->getClient()->withHeaders(['X-Device-Id' => $deviceId]);
            $response = $client->get($endpoint);

            if ($response->status() === 401) {
                return response()->json([
                    'status' => 'unauthorized',
                    'message' => 'GoWA memerlukan autentikasi. Pastikan GOWA_USER dan GOWA_PASSWORD sudah diatur di file .env Anda.',
                ], 401);
            }

            if (! $response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal memuat QR dari server (HTTP '.$response->status().').',
                ], $response->status());
            }

            $data = $response->json();
            $qrLink = $data['results']['qr_link'] ?? null;
            $qrDuration = $data['results']['qr_duration'] ?? 30;

            if (! $qrLink) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'QR Code tidak ditemukan dalam respon.',
                ], 422);
            }

            // Normalisasi qrLink agar selalu menggunakan scheme dan host dari GOWA_URL (HTTPS)
            $parsed = parse_url($qrLink);
            $baseParsed = parse_url($baseUrl);
            $scheme = $baseParsed['scheme'] ?? 'https';
            $host = $baseParsed['host'] ?? 'gowa.redscale.my.id';
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
            $directHttpsQrLink = "{$scheme}://{$host}{$path}{$query}";

            // Fetch image langsung dan jadikan base64 Data URI agar browser me-render instan (0ms extra network hop)
            $base64Image = null;
            try {
                $imgResponse = $this->getClient()->timeout(5)->get($directHttpsQrLink);
                if ($imgResponse->successful()) {
                    $mimeType = $imgResponse->header('Content-Type') ?: 'image/png';
                    $base64Image = "data:{$mimeType};base64,".base64_encode($imgResponse->body());
                }
            } catch (\Throwable) {
                // Fallback ke direct HTTPS link
            }

            $finalQrLink = $base64Image ?: $directHttpsQrLink;

            $duration = (int) $qrDuration;
            if ($duration > 0 && $duration <= 300) {
                $qrDurationMs = $duration * 1000;
            } elseif ($duration > 1000000) {
                $qrDurationMs = (int) ($duration / 1000000);
            } else {
                $qrDurationMs = $duration > 0 ? $duration : 30000;
            }

            return response()->json([
                'status' => 'success',
                'device_id' => $data['results']['device_id'] ?? $deviceId,
                'qr_link' => $finalQrLink,
                'raw_qr_link' => $directHttpsQrLink,
                'qr_duration_ms' => $qrDurationMs,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghubungi server: '.$e->getMessage(),
            ], 500);
        }
    }

    public function pairingCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = preg_replace('/[^0-9]/', '', (string) $request->input('phone'));
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $deviceId = $this->resolveOrCreateDeviceId($request->input('device_id'));

        if (empty($deviceId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menemukan atau membuat slot perangkat WhatsApp di GoWA.',
            ], 500);
        }

        try {
            $client = $this->getClient()->withHeaders(['X-Device-Id' => $deviceId]);

            $response = $client->get(rtrim($baseUrl, '/').'/app/login-with-code', [
                'phone' => $phone,
            ]);

            if ($response->status() === 401) {
                return response()->json([
                    'status' => 'unauthorized',
                    'message' => 'GoWA memerlukan autentikasi. Pastikan GOWA_USER dan GOWA_PASSWORD sudah diatur di file .env Anda.',
                ], 401);
            }

            if (! $response->successful()) {
                $data = $response->json();

                return response()->json([
                    'status' => 'error',
                    'message' => $data['message'] ?? ('Gagal mendapatkan kode pairing (HTTP '.$response->status().').'),
                ], $response->status());
            }

            $data = $response->json();
            $pairCode = $data['results']['pair_code'] ?? null;

            if (! $pairCode) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kode pairing tidak ditemukan dalam respon.',
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'pair_code' => $pairCode,
                'phone' => $phone,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghubungi server: '.$e->getMessage(),
            ], 500);
        }
    }

    public function qrImage(Request $request): Response
    {
        $url = $request->query('url');
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $allowedHost = parse_url($baseUrl, PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);

        if (strcasecmp($allowedHost, $targetHost) !== 0) {
            abort(403, 'Forbidden image source');
        }

        try {
            $response = $this->getClient()->get($url);

            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type') ?: 'image/png',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        } catch (\Throwable) {
            abort(502, 'Failed to fetch QR image');
        }
    }

    public function status(Request $request): JsonResponse
    {
        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $deviceId = $request->query('device_id') ?: config('services.gowa.device_id');

        try {
            $client = $this->getClient();

            if (! empty($deviceId)) {
                $endpoint = rtrim($baseUrl, '/').'/devices/'.urlencode($deviceId).'/status';
                $response = $client->get($endpoint);

                if ($response->successful()) {
                    $data = $response->json();
                    $results = $data['results'] ?? [];
                    $isConnected = ! empty($results['is_connected']) || ! empty($results['is_logged_in']);

                    return response()->json([
                        'connected' => $isConnected,
                        'device_id' => $deviceId,
                        'details' => $results,
                    ]);
                }
            }

            // Fallback: check all devices
            $endpoint = rtrim($baseUrl, '/').'/devices';
            $response = $client->get($endpoint);

            if (! $response->successful()) {
                return response()->json([
                    'connected' => false,
                    'status' => $response->status(),
                    'devices' => [],
                ]);
            }

            $data = $response->json();
            $isConnected = false;
            $connectedDevice = null;
            $devices = [];

            if (isset($data['results']) && is_array($data['results'])) {
                $devices = array_is_list($data['results']) ? $data['results'] : [$data['results']];
                foreach ($devices as $device) {
                    $state = strtolower($device['state'] ?? $device['status'] ?? '');
                    if ($state === 'connected' || $state === 'authenticated' || ! empty($device['is_connected']) || ! empty($device['is_logged_in'])) {
                        $isConnected = true;
                        $connectedDevice = $device;
                        break;
                    }
                }
            }

            return response()->json([
                'connected' => $isConnected,
                'device' => $connectedDevice,
                'devices' => $devices,
            ]);
        } catch (\Throwable) {
            return response()->json([
                'connected' => false,
                'devices' => [],
            ]);
        }
    }

    public function logout(string $id, Request $request): JsonResponse
    {
        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');
        $action = $request->input('action', 'logout');

        try {
            if ($action === 'delete') {
                // DELETE /devices/{id} permanently removes the device slot
                $response = $this->getClient()->delete(rtrim($baseUrl, '/').'/devices/'.urlencode($id));
            } else {
                // POST /devices/{id}/logout disconnects the WhatsApp session while keeping the slot
                $response = $this->getClient()->post(rtrim($baseUrl, '/').'/devices/'.urlencode($id).'/logout');

                if (! $response->successful() && $response->status() === 404) {
                    $response = $this->getClient()->delete(rtrim($baseUrl, '/').'/devices/'.urlencode($id));
                }
            }

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? ($action === 'delete' ? 'Perangkat berhasil dihapus.' : 'Koneksi WhatsApp berhasil diputuskan.')
                    : ($response->json('message') ?? 'Gagal memproses permintaan.'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reconnect(string $id): JsonResponse
    {
        $baseUrl = config('services.gowa.url', 'https://gowa.redscale.my.id');

        try {
            $response = $this->getClient()->post(rtrim($baseUrl, '/').'/devices/'.urlencode($id).'/reconnect');

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
