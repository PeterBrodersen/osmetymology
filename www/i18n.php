<?php

function loadTranslationCatalogue(string $filePath): array
{
    if (!is_readable($filePath)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($filePath), true);
    return is_array($decoded) ? $decoded : [];
}

function isAssociativeArray(array $value): bool
{
    return array_keys($value) !== range(0, count($value) - 1);
}

function mergeTranslationTrees(array $base, array $overrides): array
{
    $merged = $base;
    foreach ($overrides as $key => $overrideValue) {
        $baseValue = $merged[$key] ?? null;
        if (is_array($baseValue) && is_array($overrideValue) && isAssociativeArray($baseValue) && isAssociativeArray($overrideValue)) {
            $merged[$key] = mergeTranslationTrees($baseValue, $overrideValue);
            continue;
        }

        $merged[$key] = $overrideValue;
    }

    return $merged;
}

function getTranslationOverridesFromConfig(array $config): array
{
    $overrides = $config['language']['translation_overrides'] ?? [];
    return is_array($overrides) ? $overrides : [];
}

function getAvailableLocales(array $catalogue): array
{
    $locales = [];
    foreach ($catalogue as $code => $messages) {
        if ($code === 'meta' || !is_array($messages)) {
            continue;
        }

        $languageName = $messages['languageName'] ?? null;
        if (is_string($languageName) && $languageName !== '') {
            $locales[$code] = $languageName;
        }
    }

    return $locales;
}

function getDefaultLocale(array $catalogue): string
{
    $defaultLocale = $catalogue['meta']['defaultLocale'] ?? 'en';
    $availableLocales = getAvailableLocales($catalogue);
    if (isset($availableLocales[$defaultLocale])) {
        return $defaultLocale;
    }

    return array_key_first($availableLocales) ?? 'en';
}

function normalizeLocale(?string $locale, array $catalogue, ?string $fallbackLocale = null): string
{
    $availableLocales = getAvailableLocales($catalogue);
    if (is_string($locale) && isset($availableLocales[$locale])) {
        return $locale;
    }

    if (is_string($fallbackLocale) && isset($availableLocales[$fallbackLocale])) {
        return $fallbackLocale;
    }

    return getDefaultLocale($catalogue);
}

function getRequestedLocale(array $catalogue, ?string $configuredDefaultLocale = null): string
{
    $requestedLocale = null;
    if (isset($_GET['lang']) && is_string($_GET['lang'])) {
        $requestedLocale = $_GET['lang'];
    } elseif (isset($_COOKIE['language']) && is_string($_COOKIE['language'])) {
        $requestedLocale = $_COOKIE['language'];
    }

    $fallbackLocale = normalizeLocale($configuredDefaultLocale, $catalogue);
    $locale = normalizeLocale($requestedLocale, $catalogue, $fallbackLocale);
    if ($requestedLocale !== null && $requestedLocale !== '' && $requestedLocale === $locale) {
        setcookie('language', $locale, time() + 31536000, '/');
    }

    return $locale;
}

function lookupTranslationValue(array $messages, string $key)
{
    $value = $messages;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }

        $value = $value[$segment];
    }

    return $value;
}

function interpolateTranslationText(string $text, array $params): string
{
    return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static function ($matches) use ($params) {
        $paramName = $matches[1];
        return array_key_exists($paramName, $params) ? (string) $params[$paramName] : '';
    }, $text);
}

function getLocalizedConfigText(array $localizedValues, string $locale, ?string $fallbackLocale = null, string $fallbackValue = ''): string
{
    if (isset($localizedValues[$locale]) && is_string($localizedValues[$locale]) && $localizedValues[$locale] !== '') {
        return $localizedValues[$locale];
    }

    if ($fallbackLocale !== null && isset($localizedValues[$fallbackLocale]) && is_string($localizedValues[$fallbackLocale]) && $localizedValues[$fallbackLocale] !== '') {
        return $localizedValues[$fallbackLocale];
    }

    foreach ($localizedValues as $value) {
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    return $fallbackValue;
}

function buildLocaleParamsMap(array $catalogue, callable $paramBuilder): array
{
    $paramsByLocale = [];
    foreach (array_keys(getAvailableLocales($catalogue)) as $locale) {
        $paramsByLocale[$locale] = (array) $paramBuilder($locale);
    }

    return $paramsByLocale;
}

function buildConfiguredI18nContext(array $catalogue, array $config, callable $paramBuilder): array
{
    $placeName = (string) ($config['place']['name'] ?? '');
    $configuredDefaultLocale = is_string($config['language']['default_locale'] ?? null) ? $config['language']['default_locale'] : null;
    $translationOverrides = getTranslationOverridesFromConfig($config);
    $localizedProjectNames = is_array($config['language']['project_names'] ?? null) ? $config['language']['project_names'] : [];

    $translations = mergeTranslationTrees($catalogue, $translationOverrides);
    $locale = getRequestedLocale($translations, $configuredDefaultLocale);
    $fallbackLocale = normalizeLocale($configuredDefaultLocale, $translations);
    $localeParams = buildLocaleParamsMap(
        $translations,
        static function (string $localeCode) use ($paramBuilder, $localizedProjectNames, $fallbackLocale, $placeName, $config): array {
            $localizedPlaceName = getLocalizedConfigText($localizedProjectNames, $localeCode, $fallbackLocale, $placeName);
            return (array) $paramBuilder($localeCode, $localizedPlaceName, $config, $fallbackLocale, $placeName);
        }
    );

    return [
        'translations' => $translations,
        'locale' => $locale,
        'fallbackLocale' => $fallbackLocale,
        'translationOverrides' => $translationOverrides,
        'localeParams' => $localeParams,
        'translationParams' => $localeParams[$locale] ?? [],
        'placeName' => $placeName,
        'configuredDefaultLocale' => $configuredDefaultLocale,
    ];
}

function translateCatalogue(array $catalogue, string $locale, string $key, array $params = []): string
{
    $defaultLocale = getDefaultLocale($catalogue);
    $messages = $catalogue[$locale] ?? [];
    $value = lookupTranslationValue($messages, $key);

    if (!is_string($value)) {
        $fallbackMessages = $catalogue[$defaultLocale] ?? [];
        $value = lookupTranslationValue($fallbackMessages, $key);
    }

    if (!is_string($value)) {
        return $key;
    }

    return interpolateTranslationText($value, $params);
}

function loadAndInitializeConfig(string $configPath): array
{
    $decodedConfig = [];
    if (is_readable($configPath)) {
        $decoded = json_decode(file_get_contents($configPath), true);
        $decodedConfig = is_array($decoded) ? $decoded : [];
    }
    return $decodedConfig;
}

function setupPageI18nContext(string $configPath, string $translationPath, callable $paramBuilder): array
{
    $decodedConfig = loadAndInitializeConfig($configPath);
    $translations = loadTranslationCatalogue($translationPath);
    $placeName = (string) ($decodedConfig['place']['name'] ?? '');

    $i18nContext = buildConfiguredI18nContext($translations, $decodedConfig, $paramBuilder);

    return [
        'decodedConfig' => $decodedConfig,
        'placeName' => $placeName,
        'translations' => $i18nContext['translations'],
        'locale' => $i18nContext['locale'],
        'translationOverrides' => $i18nContext['translationOverrides'],
        'localeParams' => $i18nContext['localeParams'],
        'translationParams' => $i18nContext['translationParams'],
    ];
}

function buildI18nConfig(array $catalogue, string $locale, string $page, array $params = [], array $paramsByLocale = [], array $translationOverrides = []): array
{
    $availableLocales = [];
    foreach (getAvailableLocales($catalogue) as $code => $languageName) {
        $availableLocales[] = [
            'code' => $code,
            'name' => $languageName,
        ];
    }

    $defaultLocale = getDefaultLocale($catalogue);

    return [
        'locale' => $locale,
        'fallbackLocale' => $defaultLocale,
        'page' => $page,
        'params' => $params,
        'paramsByLocale' => $paramsByLocale,
        'locales' => $availableLocales,
        'fileUrl' => '/translations.json',
        'translationOverrides' => $translationOverrides,
        'currentMessages' => is_array($catalogue[$locale] ?? null) ? $catalogue[$locale] : [],
        'fallbackMessages' => is_array($catalogue[$defaultLocale] ?? null) ? $catalogue[$defaultLocale] : [],
    ];
}
