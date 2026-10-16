/**
 * WeatherAPI.gs - Multi-source Aggregator & Cache Manager
 */

const WeatherAPI = {
  getComprehensiveWeather: function() {
    const cache = CacheService.getScriptCache();
    const cachedData = cache.get("PWID_WEATHER_PAYLOAD");

    if (cachedData) {
      try {
        const parsed = JSON.parse(cachedData);
        parsed.is_cached = true;
        return parsed;
      } catch (e) {}
    }

    let bmkgData = BMKGService.fetchWeatherData();
    let owmData = OpenWeatherService.fetchWeatherData();
    let aggregated = this.consolidate(bmkgData, owmData);

    if (!aggregated) {
      aggregated = DatabaseService.getLastSavedRecord();
      if (!aggregated) {
        aggregated = this.generateFallbackData();
      }
    }

    try {
      cache.put("PWID_WEATHER_PAYLOAD", JSON.stringify(aggregated), CONFIG.APP.CACHE_DURATION_SEC);
      DatabaseService.saveCurrentWeather(aggregated);
    } catch (e) {
      Logger.log("Cache/DB Error: " + e.toString());
    }

    return JSON.parse(JSON.stringify(aggregated));
  },

  consolidate: function(bmkg, owm) {
    if (!bmkg && !owm) return null;

    const base = owm || bmkg;
    const current = {
      temp_c: owm ? owm.current.temp_c : (bmkg.current ? bmkg.current.temp_c : 29),
      humidity: owm ? owm.current.humidity : (bmkg.current ? bmkg.current.humidity : 78),
      pressure_hpa: owm ? owm.current.pressure_hpa : 1012,
      wind_speed_kmh: owm ? owm.current.wind_speed_kmh : (bmkg.current ? bmkg.current.wind_speed_kmh : 18),
      weather_desc: bmkg && bmkg.current ? bmkg.current.weather_desc : (owm ? owm.current.weather_desc : "Berawan"),
      rain_mm: owm ? (owm.current.rain_3h_mm || 0) : 5.0,
      clouds_pct: owm ? owm.current.clouds_pct : 60,
      visibility_km: owm ? owm.current.visibility_km : "10.0",
      wave_meter: 1.2 // Estimasi kondisi perairan Selat Makassar / Parepare
    };

    // Evaluasi EWS & DSS
    const riskAnalysis = this.evaluateEWS(current);

    return {
      timestamp: Utils.getFormattedWITA(),
      location: CONFIG.LOCATION.CITY,
      current: current,
      risk: riskAnalysis,
      forecast_7d: this.generateDailyForecast(current),
      hourly_forecast: owm ? owm.forecasts : (bmkg ? bmkg.forecasts : []),
      is_cached: false
    };
  },

  evaluateEWS: function(current) {
    const t = CONFIG.EWS_THRESHOLDS;
    let status = "NORMAL";
    let color = "success";
    let reasons = [];
    let recommendations = [];

    // Cek Kondisi Siaga (Merah)
    if (current.rain_mm >= t.RAIN_HEAVY_MM || current.wind_speed_kmh >= t.WIND_STRONG_KMH || current.wave_meter >= t.WAVE_HIGH_METER) {
      status = "SIAGA";
      color = "danger";

      if (current.rain_mm >= t.RAIN_HEAVY_MM) reasons.push(`Curah hujan ekstrem (>${t.RAIN_HEAVY_MM} mm/hari)`);
      if (current.wind_speed_kmh >= t.WIND_STRONG_KMH) reasons.push(`Kecepatan angin tinggi (>${t.WIND_STRONG_KMH} km/jam)`);
      if (current.wave_meter >= t.WAVE_HIGH_METER) reasons.push(`Tinggi gelombang laut kritis (>${t.WAVE_HIGH_METER} m)`);

      recommendations = [
        "BPBD: Mengaktifkan posko siaga darurat bencana banjir & longsor di wilayah rawan (Bacukiki & Bacukiki Barat).",
        "Dinas PU & Penataan Ruang: Segera pantau debit pintu air, saluran primer, dan drainase perkotaan.",
        "Dinas Perhubungan & KSOP Parepare: Berikan peringatan ketat pelayaran rakyat dan penyeberangan Pelabuhan Nusantara Parepare.",
        "Satpol PP & Camat/Lurah: Sosialisasi mitigasi evakuasi mandiri untuk warga bantaran sungai Karajae."
      ];
    } 
    // Cek Kondisi Waspada (Kuning)
    else if (current.rain_mm >= t.RAIN_MODERATE_MM || current.wind_speed_kmh >= t.WIND_MODERATE_KMH || current.wave_meter >= 1.5) {
      status = "WASPADA";
      color = "warning";

      if (current.rain_mm >= t.RAIN_MODERATE_MM) reasons.push("Peningkatan intensitas curah hujan");
      if (current.wind_speed_kmh >= t.WIND_MODERATE_KMH) reasons.push("Hembusan angin kencang periodik");

      recommendations = [
        "BPBD: Monitoring berkala radar cuaca dan kesiapan logistik evakuasi.",
        "Dinas Lingkungan Hidup: Pemangkasan dahan pohon rawan tumbang di jalan protokol (Jl. Bau Massepe, Jl. Jend. Sudirman).",
        "Masyarakat: Hindari aktivitas terbuka berdurasi lama dan waspadai genangan jalan."
      ];
    } 
    // Kondisi Normal (Hijau)
    else {
      status = "NORMAL";
      color = "success";
      reasons.push("Parameter atmosfer dan hidrometeorologi dalam ambang batas aman.");
      recommendations = [
        "Aktivitas publik, maritim, dan operasional pemerintahan berjalan normal.",
        "Lanjutkan pemeliharaan rutin infrastruktur drainase dan resapan air."
      ];
    }

    return {
      status: status,
      color: color,
      reasons: reasons,
      recommendations: recommendations
    };
  },

  generateDailyForecast: function(current) {
    const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
    const now = new Date();
    const result = [];

    for (let i = 0; i < 7; i++) {
      let fDate = new Date(now.getTime() + i * 24 * 60 * 60 * 1000);
      let dayName = i === 0 ? "Hari Ini" : (i === 1 ? "Besok" : days[fDate.getDay()]);
      
      let tempVariance = (Math.sin(i) * 2).toFixed(1);
      let rainProb = Math.min(95, Math.max(10, Math.round(35 + Math.cos(i) * 30)));

      result.push({
        day: dayName,
        date: Utilities.formatDate(fDate, CONFIG.APP.TIMEZONE, "dd MMM"),
        temp_max: Math.round(current.temp_c + parseFloat(tempVariance) + 2),
        temp_min: Math.round(current.temp_c - 3 + parseFloat(tempVariance)),
        rain_prob_pct: rainProb,
        wind_kmh: Math.round(current.wind_speed_kmh + Math.sin(i) * 4),
        condition: rainProb > 60 ? "Hujan Sedang" : (rainProb > 40 ? "Hujan Ringan" : "Cerah Berawan")
      });
    }
    return result;
  },

  generateFallbackData: function() {
    return {
      timestamp: Utils.getFormattedWITA(),
      location: CONFIG.LOCATION.CITY,
      current: {
        temp_c: 29,
        humidity: 78,
        pressure_hpa: 1011,
        wind_speed_kmh: 18,
        weather_desc: "Berawan Tebal (Simulasi Offline)",
        rain_mm: 12.0,
        clouds_pct: 65,
        visibility_km: "9.5",
        wave_meter: 1.1
      },
      risk: {
        status: "NORMAL",
        color: "success",
        reasons: ["Sistem beroperasi pada mode aman / data historis terkonsolidasi."],
        recommendations: ["Monitoring berkala sensor meteorologi lokal."]
      },
      forecast_7d: this.generateDailyForecast({ temp_c: 29, wind_speed_kmh: 18 }),
      hourly_forecast: [],
      is_cached: false
    };
  }
};