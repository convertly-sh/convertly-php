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
        $fields = array(
            'format' => $format,
            'compression' => (string) ($options['compression'] ?? 'balanced'),
            'autoOrient' => $this->booleanField((bool) ($options['auto_orient'] ?? $options['autoOrient'] ?? true)),
            'saveToStorage' => $this->booleanField((bool) ($options['save_to_storage'] ?? $options['saveToStorage'] ?? false)),
        );

        foreach (array('resize', 'resizeWidth', 'resizeHeight') as $key) {
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
        return $this->multipart('/api/media/' . $tool, $path, $fields);
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

    private function multipart(string $path, string $filePath, array $fields): array
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
        $body['files'] = new \CURLFile($filePath, $this->mimeType($filePath), basename($filePath));

        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ),
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
            return array('ok' => false, 'status' => $status, 'error' => $json['error'] ?? 'Convertly request failed.');
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
