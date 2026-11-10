<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use Throwable;

/**
 * Which site an agent is talking to, and which domains this installation serves - so "put it
 * on site.com" can be checked instead of guessed. Reported in `initialize`
 * (`serverInfo.site`): the host of this request, the site's name and base URL, and every
 * domain from sMultisite (when installed) and EVO_SITE_HOSTNAMES.
 *
 * Only those two sources are trusted. The site's base URL (EVO_SITE_URL / site_url) is not:
 * Evo derives it from the Host header unless it is pinned, so it would turn whatever host a
 * client sends into a "known" domain. The request host and the base URL are only reported when
 * this installation declares their host (or declares nothing): a Host header is the client's word.
 *
 * The domain list itself is only reported to managers with Evo's `settings` permission, the one
 * sMultisite's domain page sits behind; others get the rest without `domains`. Whether the
 * current host is declared is no secret: the site answers on it or not.
 */
final class SiteDomains
{
    /** @return array{host: ?string, url: ?string, name: string, domains?: array<int, string>} */
    public static function describe(?string $requestHost = null): array
    {
        $domains = self::all();
        $host = $requestHost !== null ? self::normalise($requestHost) : '';
        $url = ManagerUrl::siteBase();

        $site = [
            'host' => self::isTrusted($host, $domains) ? $host : null,
            'url' => self::isTrusted(self::normalise($url), $domains) ? $url : null,
            'name' => function_exists('evo') ? (string)evo()->getConfig('site_name') : '',
        ];

        if (self::mayListDomains()) {
            $site['domains'] = $domains;
        }

        return $site;
    }

    /** The current manager (session or token owner) holds `settings`; no manager identity means no. */
    private static function mayListDomains(): bool
    {
        if (!function_exists('evo')) {
            return false;
        }

        try {
            return evo()->isLoggedIn('mgr') && (bool)evo()->hasPermission('settings', 'mgr');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int, string> lower-case hosts, default ports dropped */
    public static function all(): array
    {
        $out = [];

        try {
            if (class_exists(\Seiger\sMultisite\Models\sMultisite::class)
                && class_exists(\Illuminate\Support\Facades\Schema::class)
                && \Illuminate\Support\Facades\Schema::hasTable('s_multisites')) {
                foreach (\Illuminate\Support\Facades\DB::table('s_multisites')->where('active', 1)->pluck('domain') as $domain) {
                    $out[] = self::normalise((string)$domain);
                }
            }
        } catch (Throwable) {
        }

        $list = defined('EVO_SITE_HOSTNAMES') ? (string)EVO_SITE_HOSTNAMES : (string)getenv('EVO_SITE_HOSTNAMES');
        foreach (explode(',', $list) as $host) {
            $out[] = self::normalise($host);
        }

        return array_values(array_unique(array_filter($out, static fn(string $h): bool => $h !== '')));
    }

    /** @param array<int, string> $domains */
    private static function isTrusted(string $host, array $domains): bool
    {
        return $host !== '' && ($domains === [] || in_array($host, $domains, true));
    }

    public static function normalise(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '/') {
            return '';
        }

        $url = str_contains($value, '://') ? $value : 'http://' . $value;
        $host = strtolower(trim((string)parse_url($url, PHP_URL_HOST), '.'));
        $port = parse_url($url, PHP_URL_PORT);

        return $host === '' ? '' : $host . ($port !== null && !in_array($port, [80, 443], true) ? ':' . $port : '');
    }
}
