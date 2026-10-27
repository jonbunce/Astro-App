import { Stack, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { WebView, type WebViewNavigation } from 'react-native-webview';

import { colors, SITE_URL } from '@/theme';

// The website's own header, footer and cookie bar are hidden so club pages read as part of the app.
const HIDE_SITE_CHROME = `
  (function () {
    var css = '#header, #footer, .header-wrapper, .absolute-footer, #wpadminbar, .cmplz-cookiebanner { display: none !important; }'
      + ' body { padding-top: 0 !important; }';
    var style = document.createElement('style');
    style.innerHTML = css;
    document.head.appendChild(style);
  })();
  true;
`;

// The website runs Google Analytics (Site Kit's gtag). The app promises no analytics, so before
// the page's scripts run, the Google tag's command queue is locked empty: the page's
// gtag('config', …) can't be queued and gtag.js has nothing to send.
const NO_ANALYTICS = `
  (function () {
    try {
      Object.defineProperty(window, 'dataLayer', { value: Object.freeze([]), writable: false, configurable: false });
    } catch (e) {}
  })();
  true;
`;

export default function WebPage() {
  const { url, title } = useLocalSearchParams<{ url: string; title?: string }>();
  const [loading, setLoading] = useState(true);

  // Only club pages open in the app; anything else (shop checkout, PDFs, other sites) opens in the browser.
  function allow(nav: WebViewNavigation) {
    if (nav.url.startsWith(SITE_URL) && !/\.(pdf|docx?)($|\?)/i.test(nav.url)) return true;
    void WebBrowser.openBrowserAsync(nav.url);
    return false;
  }

  return (
    <View style={styles.root}>
      <Stack.Screen options={{ title: title ?? '' }} />
      <WebView
        source={{ uri: url }}
        injectedJavaScriptBeforeContentLoaded={NO_ANALYTICS + HIDE_SITE_CHROME}
        injectedJavaScript={HIDE_SITE_CHROME}
        onShouldStartLoadWithRequest={allow}
        onLoadEnd={() => setLoading(false)}
        style={styles.web}
        sharedCookiesEnabled={false}
        incognito
      />
      {loading ? <ActivityIndicator color={colors.gold} style={StyleSheet.absoluteFill} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.carbon },
  web: { flex: 1, backgroundColor: colors.carbon },
});
