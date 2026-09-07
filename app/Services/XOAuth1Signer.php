<?php

namespace App\Services;

class XOAuth1Signer
{
    /** @param array<string, string> $oauth */
    public function authorizationHeader(string $method, string $url, array $oauth, string $consumerSecret, string $tokenSecret): string
    {
        $encoded = [];

        foreach ($oauth as $key => $value) {
            $encoded[$this->encode($key)] = $this->encode($value);
        }

        ksort($encoded);
        $parameterString = collect($encoded)
            ->map(fn (string $value, string $key): string => $key.'='.$value)
            ->implode('&');
        $signatureBase = strtoupper($method).'&'.$this->encode($url).'&'.$this->encode($parameterString);
        $signingKey = $this->encode($consumerSecret).'&'.$this->encode($tokenSecret);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));
        ksort($oauth);

        return 'OAuth '.collect($oauth)
            ->map(fn (string $value, string $key): string => $this->encode($key).'="'.$this->encode($value).'"')
            ->implode(', ');
    }

    private function encode(string $value): string
    {
        return str_replace('%7E', '~', rawurlencode($value));
    }
}
