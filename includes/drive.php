<?php

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function drive_key_ready(): bool
{
    return is_readable(DRIVE_KEY_FILE);
}

function drive_is_spreadsheet(string $name, string $mime): bool
{
    $lower = strtolower($name);

    if (str_ends_with($lower, '.xlsx')) {
        return true;
    }

    return $mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
}

function drive_access(): array
{
    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (! is_readable($autoload)) {
        return ['ok' => false, 'error' => 'Google client libraries are not installed (vendor/autoload.php missing).'];
    }

    if (! drive_key_ready()) {
        return ['ok' => false, 'error' => 'Service account key file was not found. Check DRIVE_KEY_FILE in config.'];
    }

    require_once $autoload;

    try {
        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/drive.readonly',
            DRIVE_KEY_FILE,
        );
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? '';

        if ($accessToken === '') {
            return ['ok' => false, 'error' => 'Google did not return an access token.'];
        }

        return [
            'ok' => true,
            'error' => '',
            'http' => new Client(['timeout' => 120]),
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function drive_list_files(): array
{
    $access = drive_access();

    if (! $access['ok']) {
        return ['ok' => false, 'error' => $access['error'], 'files' => [], 'folder_name' => ''];
    }

    try {
        $folderId = DRIVE_FOLDER_ID;
        $query = sprintf("'%s' in parents and trashed = false", $folderId);
        $http = $access['http'];
        $headers = $access['headers'];

        $folderResponse = $http->get('https://www.googleapis.com/drive/v3/files/' . $folderId, [
            'headers' => $headers,
            'query' => [
                'fields' => 'id,name',
                'supportsAllDrives' => 'true',
            ],
        ]);
        $folder = json_decode((string) $folderResponse->getBody(), true);

        $response = $http->get('https://www.googleapis.com/drive/v3/files', [
            'headers' => $headers,
            'query' => [
                'q' => $query,
                'fields' => 'files(id,name,mimeType,modifiedTime,size)',
                'orderBy' => 'modifiedTime desc',
                'pageSize' => 100,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        $files = $payload['files'] ?? [];

        return [
            'ok' => true,
            'error' => '',
            'folder_name' => (string) ($folder['name'] ?? ''),
            'files' => $files,
        ];
    } catch (GuzzleException $e) {
        return ['ok' => false, 'error' => 'Drive request failed: ' . $e->getMessage(), 'files' => [], 'folder_name' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'files' => [], 'folder_name' => ''];
    }
}

function drive_download_file(string $fileId): array
{
    $access = drive_access();

    if (! $access['ok']) {
        return ['ok' => false, 'error' => $access['error'], 'path' => ''];
    }

    $path = tempnam(sys_get_temp_dir(), 'casxlsx') . '.xlsx';

    try {
        $access['http']->get('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId), [
            'headers' => $access['headers'],
            'query' => [
                'alt' => 'media',
                'supportsAllDrives' => 'true',
            ],
            'sink' => $path,
        ]);

        return ['ok' => true, 'error' => '', 'path' => $path];
    } catch (GuzzleException $e) {
        @unlink($path);

        return ['ok' => false, 'error' => 'Download failed: ' . $e->getMessage(), 'path' => ''];
    } catch (Throwable $e) {
        @unlink($path);

        return ['ok' => false, 'error' => $e->getMessage(), 'path' => ''];
    }
}

function drive_format_size(?string $bytes): string
{
    if ($bytes === null || $bytes === '') {
        return '—';
    }

    $size = (float) $bytes;
    if ($size < 1024) {
        return ((int) $size) . ' B';
    }

    return number_format($size / 1024, 1) . ' KB';
}
