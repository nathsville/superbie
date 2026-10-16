/**
 * Utils.gs - Format & Sanitasi
 */
const Utils = {
  getFormattedWITA: function() {
    return Utilities.formatDate(new Date(), CONFIG.APP.TIMEZONE, "dd MMMM yyyy, HH:mm:ss 'WITA'");
  }
};

/**
 * Main.gs - RPC Controller untuk Frontend
 */
function fetchDashboardData() {
  return WeatherAPI.getComprehensiveWeather();
}

function fetchHistoricalAnalytics() {
  return DatabaseService.getAnalyticsHistory();
}

function forceRefreshData() {
  CacheService.getScriptCache().remove("PWID_WEATHER_PAYLOAD");
  return WeatherAPI.getComprehensiveWeather();
}