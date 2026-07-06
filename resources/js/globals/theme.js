/**
* Sheaf Dark Mode Theme System
* Provides comprehensive theme management with Alpine.js integration
*/

import defineReactiveMagicProperty from '../utils.js';

document.addEventListener('alpine:init', () => {
    defineReactiveMagicProperty('theme', {
        currentTheme: null,
        storedTheme: null,

        init() {
            this.storedTheme = 'light';
            this.currentTheme = 'light';
            applyTheme('light');
        },

        setTheme(newTheme) {
            // App is light-only; ignore dark/system requests
            this.storedTheme = 'light';
            this.currentTheme = 'light';
            applyTheme('light');
        },

        setLight() { this.setTheme('light'); },
        setDark() { /* dark mode disabled */ },
        setSystem() { /* dark mode disabled */ },

        toggle() { /* dark mode disabled */ },

        /**
            * Get current theme state information
            */
        get() {
            return {
                stored: this.storedTheme,
                current: this.currentTheme,
                isLight: this.isLight,
                isDark: this.isDark,
                isSystem: this.isSystem
            };
        },

        // Getter methods for easy template usage
        get isLight() {
            return this.storedTheme === 'light';
        },

        get isDark() {
            return this.storedTheme === 'dark';
        },

        get isSystem() {
            return this.storedTheme === 'system';
        },

        /**
            * Sometimes we need to show only light or dark, not system mode.
            * These getters handle scenarios where we need the resolved theme state.
            */
        get isResolvedToLight() {
            if (this.isSystem) {
                return getSystemTheme() === 'light';
            }
            return this.isLight;
        },

        get isResolvedToDark() {
            if (this.isSystem) {
                return getSystemTheme() === 'dark';
            }
            return this.isDark;
        }
    });
});

/**
    * Static helper functions
    */

function computeTheme(themePreference) {
    if (themePreference === 'system') {
        return getSystemTheme();
    }
    return themePreference;
}

function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches 
        ? 'dark' 
        : 'light';
}

function applyTheme(theme) {
    const documentElement = document.documentElement;
    
    if (theme === 'dark') {
        documentElement.classList.add('dark');
    } else {
        documentElement.classList.remove('dark');
    }
    
    // Dispatch custom event for theme change listeners
    document.dispatchEvent(new CustomEvent('theme-changed', {
        detail: { theme }
    }));
}