(function () {
    const appConfig = window.appConfig || {};
    const bootstrap = appConfig.i18n || {};

    const state = {
        page: bootstrap.page || '',
        currentLocale: bootstrap.locale || 'en',
        fallbackLocale: bootstrap.fallbackLocale || 'en',
        currentMessages: bootstrap.currentMessages || {},
        fallbackMessages: bootstrap.fallbackMessages || {},
        allTranslations: null,
        params: bootstrap.params || {},
        paramsByLocale: bootstrap.paramsByLocale || {},
        locales: Array.isArray(bootstrap.locales) ? bootstrap.locales.slice() : [],
        fileUrl: bootstrap.fileUrl || '/translations.json',
        translationOverrides: bootstrap.translationOverrides || {},
    };

    function isPlainObject(value) {
        return !!value && typeof value === 'object' && !Array.isArray(value);
    }

    function mergeTranslationTrees(base, overrides) {
        const merged = isPlainObject(base) ? { ...base } : {};
        for (const [key, overrideValue] of Object.entries(overrides || {})) {
            const baseValue = merged[key];
            if (isPlainObject(baseValue) && isPlainObject(overrideValue)) {
                merged[key] = mergeTranslationTrees(baseValue, overrideValue);
                continue;
            }

            merged[key] = overrideValue;
        }

        return merged;
    }

    function resolveKey(messages, key) {
        let value = messages;
        for (const segment of key.split('.')) {
            if (!value || typeof value !== 'object' || !(segment in value)) {
                return undefined;
            }
            value = value[segment];
        }

        return value;
    }

    function interpolate(text, params) {
        return String(text).replace(/\{\{\s*(\w+)\s*\}\}/g, (_, key) => {
            return Object.prototype.hasOwnProperty.call(params, key) ? params[key] : '';
        });
    }

    function getMessagesForLocale(locale) {
        if (state.allTranslations && state.allTranslations[locale]) {
            return state.allTranslations[locale];
        }
        if (locale === state.currentLocale) {
            return state.currentMessages;
        }
        if (locale === state.fallbackLocale) {
            return state.fallbackMessages;
        }

        return null;
    }

    function normalizeLocaleTag(locale) {
        const localeTag = translate('common.dateLocale', {}, locale);
        if (localeTag && localeTag !== 'common.dateLocale') {
            return localeTag;
        }

        return locale;
    }

    function getLocaleParams(locale) {
        return {
            ...state.params,
            ...(state.paramsByLocale[state.fallbackLocale] || {}),
            ...(state.paramsByLocale[locale] || {}),
        };
    }

    function translate(key, params = {}, locale = state.currentLocale) {
        const messages = getMessagesForLocale(locale) || {};
        let value = resolveKey(messages, key);

        if (value === undefined && locale !== state.fallbackLocale) {
            value = resolveKey(getMessagesForLocale(state.fallbackLocale) || {}, key);
        }

        if (typeof value !== 'string') {
            return key;
        }

        return interpolate(value, { ...getLocaleParams(locale), ...params });
    }

    function translatePlural(key, count, params = {}, locale = state.currentLocale) {
        const pluralKey = count === 1 ? `${key}.one` : `${key}.other`;
        return translate(pluralKey, { ...params, count: params.count ?? formatNumber(count, locale) }, locale);
    }

    function formatNumber(value, locale = state.currentLocale, options) {
        return Number(value).toLocaleString(normalizeLocaleTag(locale), options);
    }

    function formatDate(value, options) {
        const date = value instanceof Date ? value : new Date(value);
        return date.toLocaleDateString(normalizeLocaleTag(state.currentLocale), options);
    }

    function parseElementParams(element) {
        const rawParams = element.getAttribute('data-i18n-params');
        if (!rawParams) {
            return {};
        }

        try {
            const parsed = JSON.parse(rawParams);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            console.warn('Could not parse data-i18n-params', error);
            return {};
        }
    }

    function applyTranslations(root = document) {
        root.querySelectorAll('[data-i18n]').forEach((element) => {
            const params = parseElementParams(element);
            element.textContent = translate(element.getAttribute('data-i18n'), params);
        });

        root.querySelectorAll('[data-i18n-html]').forEach((element) => {
            const params = parseElementParams(element);
            element.innerHTML = translate(element.getAttribute('data-i18n-html'), params);
        });

        root.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
            const params = parseElementParams(element);
            element.setAttribute('placeholder', translate(element.getAttribute('data-i18n-placeholder'), params));
        });

        root.querySelectorAll('[data-i18n-title]').forEach((element) => {
            const params = parseElementParams(element);
            element.setAttribute('title', translate(element.getAttribute('data-i18n-title'), params));
        });

        document.documentElement.lang = state.currentLocale;
    }

    function updateAvailableLocales() {
        if (!state.allTranslations) {
            return;
        }

        const locales = [];
        for (const [code, messages] of Object.entries(state.allTranslations)) {
            if (code === 'meta' || !messages || typeof messages !== 'object') {
                continue;
            }
            if (typeof messages.languageName === 'string' && messages.languageName !== '') {
                locales.push({ code, name: messages.languageName });
            }
        }

        if (locales.length > 0) {
            state.locales = locales;
        }
    }

    function populateLanguageSelector() {
        const selector = document.getElementById('language-select');
        if (!selector) {
            return;
        }

        selector.innerHTML = '';
        for (const locale of state.locales) {
            const option = document.createElement('option');
            option.value = locale.code;
            option.textContent = locale.name;
            if (locale.code === state.currentLocale) {
                option.selected = true;
            }
            selector.appendChild(option);
        }
    }

    async function ensureTranslationsLoaded() {
        if (state.allTranslations) {
            return state.allTranslations;
        }

        const response = await fetch(state.fileUrl, { cache: 'no-cache' });
        if (!response.ok) {
            throw new Error(response.statusText);
        }

        state.allTranslations = mergeTranslationTrees(await response.json(), state.translationOverrides);
        updateAvailableLocales();
        return state.allTranslations;
    }

    async function setLanguage(locale, options = {}) {
        const { persist = true } = options;

        try {
            await ensureTranslationsLoaded();
        } catch (error) {
            console.warn('Could not load translations', error);
        }

        if (!getMessagesForLocale(locale) && !(state.allTranslations && state.allTranslations[locale])) {
            return false;
        }

        if (state.allTranslations && state.allTranslations[locale]) {
            state.currentMessages = state.allTranslations[locale];
        }
        if (state.allTranslations && state.allTranslations[state.fallbackLocale]) {
            state.fallbackMessages = state.allTranslations[state.fallbackLocale];
        }
        state.currentLocale = locale;
        applyTranslations();
        populateLanguageSelector();

        if (persist) {
            document.cookie = `language=${encodeURIComponent(locale)}; path=/; max-age=31536000; SameSite=Lax`;
        }

        document.dispatchEvent(new CustomEvent('app:languagechange', {
            detail: { locale },
        }));
        return true;
    }

    window.appI18n = {
        t: translate,
        tp: translatePlural,
        setLanguage,
        getLocale: () => state.currentLocale,
        getPage: () => state.page,
        formatDate,
        formatNumber,
        applyTranslations,
        ensureTranslationsLoaded,
    };

    document.addEventListener('DOMContentLoaded', () => {
        applyTranslations();
        populateLanguageSelector();

        const selector = document.getElementById('language-select');
        if (selector) {
            selector.addEventListener('change', (event) => {
                setLanguage(event.target.value);
            });
        }

        ensureTranslationsLoaded()
            .then(() => {
                populateLanguageSelector();
            })
            .catch((error) => {
                console.warn('Could not refresh translations from JSON', error);
            });
    });
})();