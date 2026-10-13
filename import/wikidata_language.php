<?php

function reduceWikidataLanguageMap($values, array $languages): array
{
    $reduced = [];
    foreach ($languages as $language) {
        if (is_object($values) && property_exists($values, $language)) {
            $reduced[$language] = $values->{$language};
        } elseif (is_array($values) && array_key_exists($language, $values)) {
            $reduced[$language] = $values[$language];
        }
    }
    return $reduced;
}

function reduceWikidataSitelinks($sitelinks, array $languages): array
{
    $reduced = [];
    foreach ($languages as $language) {
        $sitelinkKey = $language . 'wiki';
        if (is_object($sitelinks) && property_exists($sitelinks, $sitelinkKey)) {
            $reduced[$sitelinkKey] = $sitelinks->{$sitelinkKey};
        } elseif (is_array($sitelinks) && array_key_exists($sitelinkKey, $sitelinks)) {
            $reduced[$sitelinkKey] = $sitelinks[$sitelinkKey];
        }
    }
    return $reduced;
}

function getWikidataLocalizedValue($values, string $language): ?string
{
    $value = null;
    if (is_object($values) && property_exists($values, $language)) {
        $value = $values->{$language};
    } elseif (is_array($values) && array_key_exists($language, $values)) {
        $value = $values[$language];
    }

    if (is_object($value) && isset($value->value) && is_string($value->value)) {
        return $value->value;
    }
    if (is_array($value) && isset($value['value']) && is_string($value['value'])) {
        return $value['value'];
    }
    return is_string($value) ? $value : null;
}

function getBestWikidataValue($values, array $languages): ?string
{
    foreach ($languages as $language) {
        $value = getWikidataLocalizedValue($values, $language);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }
    return null;
}

function getWikidataSitelinkUrl(string $language, string $title): string
{
    return 'https://' . $language . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title));
}

function getBestWikidataWikipediaUrl($sitelinks, array $languages): ?string
{
    foreach ($languages as $language) {
        if ($language === 'mul') {
            continue;
        }
        $sitelinkKey = $language . 'wiki';
        $title = null;
        if (is_object($sitelinks) && isset($sitelinks->{$sitelinkKey}->title)) {
            $title = $sitelinks->{$sitelinkKey}->title;
        } elseif (is_array($sitelinks) && isset($sitelinks[$sitelinkKey])) {
            $sitelink = $sitelinks[$sitelinkKey];
            if (is_object($sitelink) && isset($sitelink->title)) {
                $title = $sitelink->title;
            } elseif (is_array($sitelink) && isset($sitelink['title'])) {
                $title = $sitelink['title'];
            }
        }
        if (is_string($title) && $title !== '') {
            return getWikidataSitelinkUrl($language, $title);
        }
    }
    return null;
}

function reduceWikidataEntityFields($entity, array $languages): array
{
    $labels = reduceWikidataLanguageMap($entity->labels ?? null, $languages);
    $descriptions = reduceWikidataLanguageMap($entity->descriptions ?? null, $languages);
    $sitelinks = reduceWikidataSitelinks($entity->sitelinks ?? null, $languages);
    $primaryLanguage = $languages[0] ?? 'en';
    $aliases = [];
    if (isset($entity->aliases->{$primaryLanguage})) {
        $aliases[$primaryLanguage] = $entity->aliases->{$primaryLanguage};
    }

    return [
        'name' => getBestWikidataValue($labels, $languages),
        'description' => getBestWikidataValue($descriptions, $languages),
        'labels' => $labels,
        'descriptions' => $descriptions,
        'sitelinks' => $sitelinks,
        'aliases' => $aliases,
        'wikipedia' => getBestWikidataWikipediaUrl($sitelinks, $languages),
    ];
}
