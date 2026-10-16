/**
 * BMKG.gs - Integrasi & Parser BMKG
 */

const BMKGService = {
  fetchWeatherData: function() {
    try {
      // Endpoint API BMKG Wilayah Sulawesi Selatan / Parepare
      const url = "https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=" + CONFIG.LOCATION.BMKG_ADM4;
      const response = UrlFetchApp.fetch(url, {
        muteHttpExceptions: true,
        headers: { "User-Agent": "Mozilla/5.0 (PWID-Executive-Dashboard)" }
      });
      
      if (response.getResponseCode() === 200) {
        const json = JSON.parse(response.getContentText());
        return this.parseBMKGResponse(json);
      }
      return null;
    } catch (err) {
      Logger.log("BMKG API Fetch Error: " + err.toString());
      return null;
    }
  },

  parseBMKGResponse: function(json) {
    try {
      if (!json || !json.data || json.data.length === 0) return null;
      
      // BMKG API mengembalikan cuaca per kecamatan dalam array cuaca berlapis
      const rawList = json.data[0].cuaca ? json.data[0].cuaca.flat() : [];
      if (rawList.length === 0) return null;

      const forecasts = rawList.slice(0, 8).map(item => ({
        datetime: item.datetime || item.local_datetime || "Hari ini",
        temp_c: parseFloat(item.t || 29),
        humidity: parseFloat(item.hu || 75),
        weather_desc: item.weather_desc || item.weather_desc_en || "Berawan",
        wind_speed_kmh: parseFloat(item.ws || 16),
        wind_dir: item.wd || "Barat Daya"
      }));

      return {
        source: "BMKG",
        current: forecasts[0] || {
          temp_c: 29,
          humidity: 75,
          weather_desc: "Cerah Berawan",
          wind_speed_kmh: 16
        },
        forecasts: forecasts
      };
    } catch (e) {
      Logger.log("Error parsing BMKG: " + e.toString());
      return null;
    }
  }
};