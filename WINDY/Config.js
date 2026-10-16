/**
 * Parepare Weather Intelligence Dashboard (PWID)
 * Config.gs - Master Configuration
 */

const CONFIG = {
  APP: {
    NAME: "Parepare Weather Intelligence Dashboard",
    ACRONYM: "PWID",
    VERSION: "1.0.0",
    TIMEZONE: "Asia/Makassar", // WITA (UTC+8)
    CACHE_DURATION_SEC: 1800 // 30 Menit Cache
  },
  
  LOCATION: {
    CITY: "Kota Parepare",
    PROVINCE: "Sulawesi Selatan",
    LATITUDE: -4.0167,
    LONGITUDE: 119.6236,
    BMKG_ADM4: "73.72.01.1001" // Kode Wilayah Parepare (Kec. Ujung / Soreang / Bacukiki)
  },

  DATABASE: {
    SPREADSHEET_NAME: "PWID_DATABASE",
    SHEETS: {
      CURRENT: "Weather_Current",
      HISTORY: "Weather_History",
      ALERT: "Weather_Alert"
    }
  },

  API_KEYS: {
    // Masukkan API Key OpenWeather Anda melalui Script Properties (Project Settings > Script Properties)
    // Key: OPENWEATHER_API_KEY
    OPENWEATHER: PropertiesService.getScriptProperties().getProperty("OPENWEATHER_API_KEY") || ""
  },

  EWS_THRESHOLDS: {
    RAIN_HEAVY_MM: 50.0,       // > 50 mm/hari -> Siaga
    RAIN_MODERATE_MM: 20.0,    // > 20 mm/hari -> Waspada
    WIND_STRONG_KMH: 40.0,     // > 40 km/jam -> Siaga
    WIND_MODERATE_KMH: 25.0,   // > 25 km/jam -> Waspada
    WAVE_HIGH_METER: 2.0       // > 2 meter -> Siaga Gelombang Laut
  }
};