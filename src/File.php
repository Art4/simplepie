<?php

// SPDX-FileCopyrightText: 2004-2023 Ryan Parman, Sam Sneddon, Ryan McCue
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace SimplePie;

use SimplePie\HTTP\Response;

/**
 * Used for fetching remote files and reading local files
 *
 * Supports HTTP 1.0 via cURL or fsockopen, with spotty HTTP 1.1 support
 *
 * This class can be overloaded with {@see \SimplePie\SimplePie::set_file_class()}
 *
 * @todo Move to properly supporting RFC2616 (HTTP/1.1)
 */
class File implements Response
{
    /** @var string The final URL after following all redirects */
    public $url;
    public $useragent;
    public $success = true;
    public $headers = [];
    public $body;
    public $status_code = 0;
    public $redirects = 0;
    public $error;
    public $method = \SimplePie\SimplePie::FILE_SOURCE_NONE;
    /** @var string The permanent URL or the resource (first URL after the prefix of (only) permanent redirects) */
    public $permanent_url;
    /** @var bool Whether the permanent URL is still writeable (prefix of permanent redirects has not ended) */
    private $permanentUrlMutable = true;

    public function __construct($url, $timeout = 10, $redirects = 5, $headers = null, $useragent = null, $force_fsockopen = false, $curl_options = [])
    {
        if (function_exists('idn_to_ascii')) {
            $parsed = \SimplePie\Misc::parse_url($url);
            if ($parsed['authority'] !== '' && !ctype_print($parsed['authority'])) {
                $authority = \idn_to_ascii($parsed['authority'], \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);
                $url = \SimplePie\Misc::compress_parse_url($parsed['scheme'], $authority, $parsed['path'], $parsed['query'], null);
            }
        }
        $this->url = $url;
        if ($this->permanentUrlMutable) {
            $this->permanent_url = $url;
        }
        $this->useragent = $useragent;
        if (preg_match('/^http(s)?:\/\//i', $url)) {
            if ($useragent === null) {
                $useragent = ini_get('user_agent');
                $this->useragent = $useragent;
            }
            if (!is_array($headers)) {
                $headers = [];
            }
            if (!$force_fsockopen && function_exists('curl_exec')) {
                $this->method = \SimplePie\SimplePie::FILE_SOURCE_REMOTE | \SimplePie\SimplePie::FILE_SOURCE_CURL;
                $fp = curl_init();
                $headers2 = [];
                foreach ($headers as $key => $value) {
                    $headers2[] = "$key: $value";
                }
                if (version_compare(\SimplePie\Misc::get_curl_version(), '7.10.5', '>=')) {
                    curl_setopt($fp, CURLOPT_ENCODING, '');
                }
                curl_setopt($fp, CURLOPT_URL, $url);
                curl_setopt($fp, CURLOPT_HEADER, 1);
                curl_setopt($fp, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($fp, CURLOPT_FAILONERROR, 1);
                curl_setopt($fp, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($fp, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($fp, CURLOPT_REFERER, \SimplePie\Misc::url_remove_credentials($url));
                curl_setopt($fp, CURLOPT_USERAGENT, $useragent);
                curl_setopt($fp, CURLOPT_HTTPHEADER, $headers2);
                foreach ($curl_options as $curl_param => $curl_value) {
                    curl_setopt($fp, $curl_param, $curl_value);
                }

                $this->headers = curl_exec($fp);
                if (curl_errno($fp) === 23 || curl_errno($fp) === 61) {
                    curl_setopt($fp, CURLOPT_ENCODING, 'none');
                    $this->headers = curl_exec($fp);
                }
                $this->status_code = curl_getinfo($fp, CURLINFO_HTTP_CODE);
                if (curl_errno($fp)) {
                    $this->error = 'cURL error ' . curl_errno($fp) . ': ' . curl_error($fp);
                    $this->success = false;
                } else {
                    // Use the updated url provided by curl_getinfo after any redirects.
                    if ($info = curl_getinfo($fp)) {
                        $this->url = $info['url'];
                    }
                    curl_close($fp);
                    $this->headers = \SimplePie\HTTP\Parser::prepareHeaders($this->headers, $info['redirect_count'] + 1);
                    $parser = new \SimplePie\HTTP\Parser($this->headers);
                    if ($parser->parse()) {
                        $this->headers = $parser->headers;
                        $this->body = trim($parser->body);
                        $this->status_code = $parser->status_code;
                        if ((in_array($this->status_code, [300, 301, 302, 303, 307]) || $this->status_code > 307 && $this->status_code < 400) && isset($this->headers['location']) && $this->redirects < $redirects) {
                            $this->redirects++;
                            $location = \SimplePie\Misc::absolutize_url($this->headers['location'], $url);
                            $this->permanentUrlMutable = $this->permanentUrlMutable && ($this->status_code == 301 || $this->status_code == 308);
                            $this->__construct($location, $timeout, $redirects, $headers, $useragent, $force_fsockopen, $curl_options);
                            return;
                        }
                    }
                }
            } else {
                $this->method = \SimplePie\SimplePie::FILE_SOURCE_REMOTE | \SimplePie\SimplePie::FILE_SOURCE_FSOCKOPEN;
                $url_parts = parse_url($url);
                $socket_host = $url_parts['host'];
                if (isset($url_parts['scheme']) && strtolower($url_parts['scheme']) === 'https') {
                    $socket_host = "ssl://$url_parts[host]";
                    $url_parts['port'] = 443;
                }
                if (!isset($url_parts['port'])) {
                    $url_parts['port'] = 80;
                }
                $fp = @fsockopen($socket_host, $url_parts['port'], $errno, $errstr, $timeout);
                if (!$fp) {
                    $this->error = 'fsockopen error: ' . $errstr;
                    $this->success = false;
                } else {
                    stream_set_timeout($fp, $timeout);
                    if (isset($url_parts['path'])) {
                        if (isset($url_parts['query'])) {
                            $get = "$url_parts[path]?$url_parts[query]";
                        } else {
                            $get = $url_parts['path'];
                        }
                    } else {
                        $get = '/';
                    }
                    $out = "GET $get HTTP/1.1\r\n";
                    $out .= "Host: $url_parts[host]\r\n";
                    $out .= "User-Agent: $useragent\r\n";
                    if (extension_loaded('zlib')) {
                        $out .= "Accept-Encoding: x-gzip,gzip,deflate\r\n";
                    }

                    if (isset($url_parts['user']) && isset($url_parts['pass'])) {
                        $out .= "Authorization: Basic " . base64_encode("$url_parts[user]:$url_parts[pass]") . "\r\n";
                    }
                    foreach ($headers as $key => $value) {
                        $out .= "$key: $value\r\n";
                    }
                    $out .= "Connection: Close\r\n\r\n";
                    fwrite($fp, $out);

                    $info = stream_get_meta_data($fp);

                    $this->headers = '';
                    while (!$info['eof'] && !$info['timed_out']) {
                        $this->headers .= fread($fp, 1160);
                        $info = stream_get_meta_data($fp);
                    }
                    if (!$info['timed_out']) {
                        $parser = new \SimplePie\HTTP\Parser($this->headers);
                        if ($parser->parse()) {
                            $this->headers = $parser->headers;
                            $this->body = $parser->body;
                            $this->status_code = $parser->status_code;
                            if ((in_array($this->status_code, [300, 301, 302, 303, 307]) || $this->status_code > 307 && $this->status_code < 400) && isset($this->headers['location']) && $this->redirects < $redirects) {
                                $this->redirects++;
                                $location = \SimplePie\Misc::absolutize_url($this->headers['location'], $url);
                                $this->permanentUrlMutable = $this->permanentUrlMutable && ($this->status_code == 301 || $this->status_code == 308);
                                $this->__construct($location, $timeout, $redirects, $headers, $useragent, $force_fsockopen, $curl_options);
                                return;
                            }
                            if (isset($this->headers['content-encoding'])) {
                                // Hey, we act dumb elsewhere, so let's do that here too
                                switch (strtolower(trim($this->headers['content-encoding'], "\x09\x0A\x0D\x20"))) {
                                    case 'gzip':
                                    case 'x-gzip':
                                        $decoder = new \SimplePie\Gzdecode($this->body);
                                        if (!$decoder->parse()) {
                                            $this->error = 'Unable to decode HTTP "gzip" stream';
                                            $this->success = false;
                                        } else {
                                            $this->body = trim($decoder->data);
                                        }
                                        break;

                                    case 'deflate':
                                        if (($decompressed = gzinflate($this->body)) !== false) {
                                            $this->body = $decompressed;
                                        } elseif (($decompressed = gzuncompress($this->body)) !== false) {
                                            $this->body = $decompressed;
                                        } elseif (function_exists('gzdecode') && ($decompressed = gzdecode($this->body)) !== false) {
                                            $this->body = $decompressed;
                                        } else {
                                            $this->error = 'Unable to decode HTTP "deflate" stream';
                                            $this->success = false;
                                        }
                                        break;

                                    default:
                                        $this->error = 'Unknown content coding';
                                        $this->success = false;
                                }
                            }
                        }
                    } else {
                        $this->error = 'fsocket timed out';
                        $this->success = false;
                    }
                    fclose($fp);
                }
            }
        } else {
            $this->method = \SimplePie\SimplePie::FILE_SOURCE_LOCAL | \SimplePie\SimplePie::FILE_SOURCE_FILE_GET_CONTENTS;
            if (empty($url) || !($this->body = trim(file_get_contents($url)))) {
                $this->error = 'file_get_contents could not read the file';
                $this->success = false;
            }
        }
    }

    /**
     * Return the string representation as a URI reference.
     *
     * Depending on which components of the URI are present, the resulting
     * string is either a full URI or relative reference according to RFC 3986,
     * Section 4.1. The method concatenates the various components of the URI,
     * using the appropriate delimiters:
     *
     * - If a scheme is present, it MUST be suffixed by ":".
     * - If an authority is present, it MUST be prefixed by "//".
     * - The path can be concatenated without delimiters. But there are two
     *   cases where the path has to be adjusted to make the URI reference
     *   valid as PHP does not allow to throw an exception in __toString():
     *     - If the path is rootless and an authority is present, the path MUST
     *       be prefixed by "/".
     *     - If the path is starting with more than one "/" and no authority is
     *       present, the starting slashes MUST be reduced to one.
     * - If a query is present, it MUST be prefixed by "?".
     * - If a fragment is present, it MUST be prefixed by "#".
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.1
     * @return string the original (first requested) URL before following redirects (expect 301)
     */
    public function get_permanent_uri(): string
    {
        return (string) $this->permanent_url;
    }

    /**
     * Return the string representation as a URI reference.
     *
     * Depending on which components of the URI are present, the resulting
     * string is either a full URI or relative reference according to RFC 3986,
     * Section 4.1. The method concatenates the various components of the URI,
     * using the appropriate delimiters:
     *
     * - If a scheme is present, it MUST be suffixed by ":".
     * - If an authority is present, it MUST be prefixed by "//".
     * - The path can be concatenated without delimiters. But there are two
     *   cases where the path has to be adjusted to make the URI reference
     *   valid as PHP does not allow to throw an exception in __toString():
     *     - If the path is rootless and an authority is present, the path MUST
     *       be prefixed by "/".
     *     - If the path is starting with more than one "/" and no authority is
     *       present, the starting slashes MUST be reduced to one.
     * - If a query is present, it MUST be prefixed by "?".
     * - If a fragment is present, it MUST be prefixed by "#".
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.1
     * @return string the final requested url after following redirects
     */
    public function get_requested_uri(): string
    {
        return (string) $this->url;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function get_status_code(): int
    {
        return (int) $this->status_code;
    }

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->get_headers() as $name => $values) {
     *         echo $name . ': ' . implode(', ', $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->get_headers() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * While header names are not case-sensitive, get_headers() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers.
     *     Each key MUST be a header name, and each value MUST be an array of
     *     strings for that header.
     */
    public function get_headers(): array
    {
        $headers = [];

        foreach (array_keys($this->headers) as $name) {
            $headers[$name] = $this->get_from_headers($name);
        }

        return $headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function has_header(string $name): bool
    {
        return $this->get_from_headers($name) !== [];
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function get_header(string $name): array
    {
        return $this->get_from_headers($name);
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function get_header_line(string $name): string
    {
        return implode(', ', $this->get_from_headers($name));
    }

    /**
     * get the body as string
     *
     * @return string
     */
    public function get_body_content(): string
    {
        return (string) $this->body;
    }

    private function get_from_headers(string $name): array
    {
        if (!array_key_exists(strtolower($name), $this->headers)) {
            return [];
        }

        $header = $this->headers[strtolower($name)];

        if (! is_array($header)) {
            if (strpos($header, ',') === false) {
                $header = [$header];
            } else {
                $header_lines = explode(',', $header);
                $header = [];
                foreach ($header_lines as $header_line) {
                    $header[] = trim($header_line);
                }
            }
        }

        return $header;
    }
}

class_alias('SimplePie\File', 'SimplePie_File');
