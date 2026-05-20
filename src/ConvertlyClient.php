<?php

declare(strict_types=1);

namespace Convertly;

final class ConvertlyClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://convertly.sh')
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function getJobs(int $limit = 20): array
    {
        return $this->requestJson('GET', '/api/jobs?limit=' . max(1, $limit));
    }

    public function getJob(string $jobId): array
    {
        return $this->requestJson('GET', '/api/jobs/' . rawurlencode($jobId));
    }

    public function getFiles(?string $folderId = null, int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $query = array(
            'limit' => max(1, min(100, $limit)),
            'offset' => max(0, $offset),
        );
        if ($folderId !== null && $folderId !== '') {
            $query['folderId'] = $folderId;
        } elseif ($folderId === '') {
            $query['folderId'] = 'null';
        }
        if (trim($search) !== '') {
            $query['q'] = trim($search);
        }

        return $this->requestJson('GET', '/api/files?' . http_build_query($query));
    }

    public function getFolders(?string $parentId = null, string $search = ''): array
    {
        $query = array();
        if ($parentId !== null && $parentId !== '') {
            $query['parentId'] = $parentId;
        } elseif ($parentId === '') {
            $query['parentId'] = 'null';
        }
        if (trim($search) !== '') {
            $query['q'] = trim($search);
        }

        return $this->requestJson('GET', '/api/folders' . ($query ? '?' . http_build_query($query) : ''));
    }

    public function createJob(array $paths, array $fields): array
    {
        $fields['saveToStorage'] = 'true';
        return $this->multipartMany('/api/jobs', $paths, $fields);
    }

    public function compressFile(string $path, array $options = array()): array
    {
        return $this->multipart('/api/compress', $path, array(
            'mode' => (string) ($options['mode'] ?? 'quality'),
            'quality' => (string) ($options['quality'] ?? 82),
            'stripMetadata' => $this->booleanField((bool) ($options['strip_metadata'] ?? $options['stripMetadata'] ?? false)),
            'saveToStorage' => $this->booleanField((bool) ($options['save_to_storage'] ?? $options['saveToStorage'] ?? false)),
        ));
    }

    public function convertFile(string $path, string $format, array $options = array()): array
    {
        // /api/convert's schema requires a numeric compression (1..100). Map
        // legacy named presets to quality levels so existing callers keep
        // working without breaking the API contract.
        $compression = $options['compression'] ?? 82;
        if (is_string($compression) && !is_numeric($compression)) {
            $presets = array(
                'fast' => 65,
                'low' => 65,
                'balanced' => 82,
                'medium' => 82,
                'careful' => 92,
                'high' => 92,
                'lossless' => 100,
            );
            $compression = $presets[strtolower($compression)] ?? 82;
        }
        $compression = max(1, min(100, (int) $compression));

        $fields = array(
            'format' => $format,
            'compression' => (string) $compression,
            'autoOrient' => $this->booleanField((bool) ($options['auto_orient'] ?? $options['autoOrient'] ?? true)),
            'mono' => $this->booleanField((bool) ($options['mono'] ?? false)),
            'saveToStorage' => $this->booleanField((bool) ($options['save_to_storage'] ?? $options['saveToStorage'] ?? false)),
        );

        foreach (array('resize', 'resizeWidth', 'resizeHeight', 'vectorize') as $key) {
            if (isset($options[$key]) && $options[$key] !== '') {
                $fields[$key] = (string) $options[$key];
            }
        }

        if (isset($options['resize_width']) && $options['resize_width'] !== '') {
            $fields['resizeWidth'] = (string) abs((int) $options['resize_width']);
        }
        if (isset($options['resize_height']) && $options['resize_height'] !== '') {
            $fields['resizeHeight'] = (string) abs((int) $options['resize_height']);
        }

        return $this->multipart('/api/convert', $path, $fields);
    }

    public function mediaTool(string $tool, string $path, array $fields = array()): array
    {
        $tool = trim($tool, '/');
        return $this->multipart('/api/media/' . $tool, $path, $fields, 'file');
    }

    public function uploadFile(string $path, ?string $folderId = null, ?string $filename = null): array
    {
        $fields = array();
        if ($folderId !== null && $folderId !== '') {
            $fields['folderId'] = $folderId;
        }
        return $this->multipart('/api/files', $path, $fields, 'file', $filename);
    }

    private function requestJson(string $method, string $path): array
    {
        if (!$this->hasApiKey()) {
            return array('ok' => false, 'error' => 'Missing Convertly API key.');
        }
        if (!function_exists('curl_init')) {
            return array('ok' => false, 'error' => 'Convertly requests require the PHP cURL extension.');
        }

        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ),
        ));

        return $this->execute($curl);
    }

    private function multipart(string $path, string $filePath, array $fields, string $fileField = 'files', ?string $filename = null): array
    {
        if (!$this->hasApiKey()) {
            return array('ok' => false, 'error' => 'Missing Convertly API key.');
        }
        if (!is_readable($filePath)) {
            return array('ok' => false, 'error' => 'File is not readable.');
        }
        if (!function_exists('curl_init') || !class_exists('CURLFile')) {
            return array('ok' => false, 'error' => 'Convertly uploads require the PHP cURL extension.');
        }

        $body = $fields;
        $uploadName = $filename !== null && $filename !== '' ? basename($filename) : basename($filePath);
        $body[$fileField] = new \CURLFile($filePath, $this->mimeType($filePath), $uploadName);

        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        );
        if (!empty($fields['idempotencyKey'])) {
            $headers[] = 'Idempotency-Key: ' . (string) $fields['idempotencyKey'];
        }

        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $headers,
        ));

        return $this->execute($curl);
    }

    private function multipartMany(string $path, array $filePaths, array $fields): array
    {
        if (!$this->hasApiKey()) {
            return array('ok' => false, 'error' => 'Missing Convertly API key.');
        }
        if (!function_exists('curl_init') || !class_exists('CURLFile')) {
            return array('ok' => false, 'error' => 'Convertly uploads require the PHP cURL extension.');
        }

        $body = $fields;
        $paths = array_values($filePaths);
        foreach ($paths as $index => $filePath) {
            if (!is_readable($filePath)) {
                return array('ok' => false, 'error' => 'File is not readable.');
            }
            $key = count($paths) === 1 ? 'files' : 'files[' . $index . ']';
            $body[$key] = new \CURLFile($filePath, $this->mimeType($filePath), basename($filePath));
        }

        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        );
        if (!empty($fields['idempotencyKey'])) {
            $headers[] = 'Idempotency-Key: ' . (string) $fields['idempotencyKey'];
        }

        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $headers,
        ));

        return $this->execute($curl);
    }

    private function execute($curl): array
    {
        $raw = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($raw === false) {
            return array('ok' => false, 'status' => $status, 'error' => $error ?: 'Convertly request failed.');
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return array('ok' => false, 'status' => $status, 'error' => 'Convertly returned an invalid response.');
        }

        if ($status < 200 || $status >= 300) {
            $result = array('ok' => false, 'status' => $status, 'error' => $json['error'] ?? 'Convertly request failed.');
            if (isset($json['code'])) {
                $result['code'] = (string) $json['code'];
            }
            if (isset($json['detail'])) {
                $result['detail'] = (string) $json['detail'];
            }
            return $result;
        }

        return array('ok' => true, 'status' => $status, 'body' => $json);
    }

    private function mimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    private function booleanField(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
