/**
 * OpenWeather.gs - Integrasi OpenWeatherMap API
 */

const OpenWeatherService = {
  fetchWeatherData: function() {
    const apiKey = CONFIG.API_KEYS.OPENWEATHER;
    if (!apiKey) {
      Logger.log("OpenWeather API Key belum dikonfigurasi.");
      return null;
    }

    try {
      const url = `https://api.openweathermap.org/data/2.5/forecast?lat=${CONFIG.LOCATION.LATITUDE}&lon=${CONFIG.LOCATION.LONGITUDE}&appid=${apiKey}&units=metric&lang=id`;
      const response = UrlFetchApp.fetch(url, { muteHttpExceptions: true });

      if (response.getResponseCode() === 200) {
        const data = JSON.parse(response.getContentText());
        return this.parseResponse(data);
      }
      return null;
    } catch (err) {
      Logger.log("OpenWeather Fetch Error: " + err.toString());
      return null;
    }
  },

  parseResponse: function(data) {
    if (!data || !data.list) return null;

    const list = data.list;
    const current = list[0];

    const forecasts = list.slice(0, 8).map(item => ({
      datetime: item.dt_txt,
      temp_c: Math.round(item.main.temp),
      humidity: item.main.humidity,
      pressure_hpa: item.main.pressure,
      weather_desc: item.weather[0].description,
      wind_speed_kmh: Math.round(item.wind.speed * 3.6),
      rain_3h_mm: item.rain ? item.rain["3h"] || 0 : 0,
      clouds_pct: item.clouds.all
    }));

    return {
      source: "OpenWeather",
      current: {
        temp_c: Math.round(current.main.temp),
        humidity: current.main.humidity,
        pressure_hpa: current.main.pressure,
        weather_desc: current.weather[0].description,
        wind_speed_kmh: Math.round(current.wind.speed * 3.6),
        clouds_pct: current.clouds.all,
        visibility_km: (current.visibility / 1000).toFixed(1)
      },
      forecasts: forecasts
    };
  }
};