/**
 * Trigger.gs - Automated Background Jobs & Time-Driven Triggers
 */

/**
 * 1. Fungsi eksekutor yang dijalankan otomatis setiap jam oleh Google Trigger
 */
function recordHourlyWeatherJob() {
  try {
    // Hapus cache sementara agar trigger selalu mengambil data cuaca paling baru
    CacheService.getScriptCache().remove("PWID_WEATHER_PAYLOAD");
    
    // Tarik data cuaca terbaru (otomatis mencatat ke sheet Weather_Current & Weather_Alert via DatabaseService)
    const weatherData = WeatherAPI.getComprehensiveWeather();
    
    // Konsolidasi ringkasan ke sheet Weather_History
    if (weatherData && weatherData.current) {
      appendHourlyToHistory(weatherData);
    }
    
    Logger.log("Job Pencatatan Cuaca Per Jam Berhasil: " + new Date().toISOString());
  } catch (err) {
    Logger.log("Job Pencatatan Cuaca Gagal: " + err.toString());
  }
}

/**
 * Helper untuk mencatat ringkasan ke Sheet 'Weather_History'
 */
function appendHourlyToHistory(data) {
  try {
    const ss = DatabaseService.getDatabaseSpreadsheet();
    let sHist = ss.getSheetByName(CONFIG.DATABASE.SHEETS.HISTORY);
    
    if (!sHist) {
      sHist = ss.insertSheet(CONFIG.DATABASE.SHEETS.HISTORY);
      sHist.appendRow(["Date", "Time", "Temp (°C)", "Humidity (%)", "Wind (km/h)", "Rain (mm)", "Condition"]);
    }
    
    const now = new Date();
    const dateStr = Utilities.formatDate(now, CONFIG.APP.TIMEZONE, "yyyy-MM-dd");
    const timeStr = Utilities.formatDate(now, CONFIG.APP.TIMEZONE, "HH:mm:ss");
    const c = data.current;

    sHist.appendRow([
      dateStr,
      timeStr,
      c.temp_c,
      c.humidity,
      c.wind_speed_kmh,
      c.rain_mm,
      c.weather_desc
    ]);
  } catch (e) {
    Logger.log("Gagal menulis ke Weather_History: " + e.toString());
  }
}

/**
 * 2. Jalankan fungsi ini SEKALI SAJA dari editor untuk memasang trigger otomatis
 */
function setupHourlyTrigger() {
  // Hapus trigger lama jika sudah ada untuk menghindari duplikasi
  removeExistingTriggers("recordHourlyWeatherJob");

  // Buat pemicu berbasis waktu (Time-driven) setiap 1 jam
  ScriptApp.newTrigger("recordHourlyWeatherJob")
    .timeBased()
    .everyHours(1)
    .create();

  Logger.log("Pemicu otomatis per jam (Hourly Trigger) berhasil diaktifkan!");
}

/**
 * Helper untuk membersihkan trigger duplikat
 */
function removeExistingTriggers(functionName) {
  const triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(trigger => {
    if (trigger.getHandlerFunction() === functionName) {
      ScriptApp.deleteTrigger(trigger);
    }
  });
}