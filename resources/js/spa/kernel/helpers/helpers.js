window.debounceTimeout = null;

window.debounce = (callback, delay = 500) => {
	if (window.debounceTimeout) {
		clearTimeout(window.debounceTimeout);
	}
	
	window.debounceTimeout = setTimeout(() => {
		callback();
	}, delay);
};

window.isStandalone = () => {
	return window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
};

window.getThemePreference = (fallback = 'light') => {
	const allowedThemes = ['light', 'dark', 'system'];

	if (typeof embedder === 'function') {
		const embeddedTheme = embedder('theme_preference', fallback);

		if (allowedThemes.includes(embeddedTheme)) {
			return embeddedTheme;
		}
	}

	try {
		const storedTheme = localStorage.getItem('theme');

		if (allowedThemes.includes(storedTheme)) {
			return storedTheme;
		}
	} catch (error) {
		//
	}

	return allowedThemes.includes(fallback) ? fallback : 'light';
};

window.resolveThemeMode = (preference = window.getThemePreference()) => {
	if (preference == 'system') {
		return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	}

	return ['light', 'dark'].includes(preference) ? preference : 'light';
};

window.writeThemeRuntimeCookie = (theme) => {
	if (!['light', 'dark'].includes(theme) || typeof document == 'undefined') {
		return;
	}

	document.cookie = `theme_runtime=${theme}; path=/; max-age=${60 * 60 * 24 * 365 * 3}`;
};

window.applyThemeMode = (theme) => {
	if (!['light', 'dark'].includes(theme) || typeof document == 'undefined') {
		return;
	}

	document.documentElement.dataset.theme = theme;
	document.documentElement.style.colorScheme = (theme == 'dark') ? 'dark' : 'light';

	if (document.body) {
		document.body.dataset.theme = theme;
	}
};

window.syncThemeMode = () => {
	const preference = window.getThemePreference();
	const resolvedTheme = window.resolveThemeMode(preference);

	window.writeThemeRuntimeCookie(resolvedTheme);
	window.applyThemeMode(resolvedTheme);

	if (typeof embedder === 'function') {
		const serverTheme = embedder('theme', resolvedTheme);
		const reloadKey = '__zulors_theme_sync__';

		if (preference == 'system' && serverTheme != resolvedTheme) {
			try {
				const savedResolvedTheme = sessionStorage.getItem(reloadKey);

				if (savedResolvedTheme != resolvedTheme) {
					sessionStorage.setItem(reloadKey, resolvedTheme);
					window.location.reload();
				} else {
					sessionStorage.removeItem(reloadKey);
				}
			} catch (error) {
				window.location.reload();
			}
		} else {
			try {
				sessionStorage.removeItem(reloadKey);
			} catch (error) {
				//
			}
		}
	}

	return {
		preference,
		resolvedTheme
	};
};

window.observeSystemThemeMode = () => {
	const preference = window.getThemePreference();

	if (preference != 'system' || typeof window.matchMedia != 'function') {
		return;
	}

	const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

	const handleChange = () => {
		const resolvedTheme = mediaQuery.matches ? 'dark' : 'light';
		const currentTheme = window.resolveThemeMode('system');

		if (resolvedTheme != currentTheme) {
			window.writeThemeRuntimeCookie(resolvedTheme);
			window.applyThemeMode(resolvedTheme);
			window.location.reload();
		}
	};

	if (typeof mediaQuery.addEventListener == 'function') {
		mediaQuery.addEventListener('change', handleChange);
	} else if (typeof mediaQuery.addListener == 'function') {
		mediaQuery.addListener(handleChange);
	}
};
