/**
 * Database.gs - Google Sheets Persistence Layer
 */

const DatabaseService = {
  getDatabaseSpreadsheet: function() {
    const props = PropertiesService.getScriptProperties();
    let ssId = props.getProperty("PWID_SPREADSHEET_ID");

    if (ssId) {
      try {
        return SpreadsheetApp.openById(ssId);
      } catch (e) {
        Logger.log("Spreadsheet ID lama tidak valid, membuat baru...");
      }
    }

    // Cari atau buat file spreadsheet baru
    const files = DriveApp.getFilesByName(CONFIG.DATABASE.SPREADSHEET_NAME);
    let ss;
    if (files.hasNext()) {
      ss = SpreadsheetApp.open(files.next());
    } else {
      ss = SpreadsheetApp.create(CONFIG.DATABASE.SPREADSHEET_NAME);
      this.initializeSheets(ss);
    }

    props.setProperty("PWID_SPREADSHEET_ID", ss.getId());
    return ss;
  },

  initializeSheets: function(ss) {
    const sCurrent = ss.getSheetByName("Sheet1") || ss.insertSheet(CONFIG.DATABASE.SHEETS.CURRENT);
    sCurrent.setName(CONFIG.DATABASE.SHEETS.CURRENT);
    sCurrent.clear();
    sCurrent.appendRow(["Timestamp (WITA)", "Temperature (°C)", "Humidity (%)", "Wind (km/h)", "Rain (mm)", "Pressure (hPa)", "Condition"]);
    sCurrent.getRange(1, 1, 1, 7).setFontWeight("bold").setBackground("#1A365D").setFontColor("#FFFFFF");

    let sHist = ss.getSheetByName(CONFIG.DATABASE.SHEETS.HISTORY);
    if (!sHist) sHist = ss.insertSheet(CONFIG.DATABASE.SHEETS.HISTORY);
    sHist.clear();
    sHist.appendRow(["Date", "Time", "Temp_Avg", "Humidity_Avg", "Wind_Max", "Rain_Total", "Dominant_Condition"]);
    sHist.getRange(1, 1, 1, 7).setFontWeight("bold").setBackground("#1A365D").setFontColor("#FFFFFF");

    let sAlert = ss.getSheetByName(CONFIG.DATABASE.SHEETS.ALERT);
    if (!sAlert) sAlert = ss.insertSheet(CONFIG.DATABASE.SHEETS.ALERT);
    sAlert.clear();
    sAlert.appendRow(["Timestamp", "Status", "Trigger_Parameters", "Government_Recommendations"]);
    sAlert.getRange(1, 1, 1, 4).setFontWeight("bold").setBackground("#742A2A").setFontColor("#FFFFFF");
  },

  saveCurrentWeather: function(payload) {
    try {
      const ss = this.getDatabaseSpreadsheet();
      const sCurrent = ss.getSheetByName(CONFIG.DATABASE.SHEETS.CURRENT);
      const c = payload.current;

      sCurrent.appendRow([
        payload.timestamp,
        c.temp_c,
        c.humidity,
        c.wind_speed_kmh,
        c.rain_mm,
        c.pressure_hpa,
        c.weather_desc
      ]);

      // Catat ke Alert jika status SIAGA atau WASPADA
      if (payload.risk && payload.risk.status !== "NORMAL") {
        const sAlert = ss.getSheetByName(CONFIG.DATABASE.SHEETS.ALERT);
        sAlert.appendRow([
          payload.timestamp,
          payload.risk.status,
          payload.risk.reasons.join(" | "),
          payload.risk.recommendations.join(" | ")
        ]);
      }
    } catch (e) {
      Logger.log("Gagal menyimpan ke Database: " + e.toString());
    }
  },

  getLastSavedRecord: function() {
    try {
      const ss = this.getDatabaseSpreadsheet();
      const sCurrent = ss.getSheetByName(CONFIG.DATABASE.SHEETS.CURRENT);
      const lastRow = sCurrent.getLastRow();
      if (lastRow <= 1) return null;

      const data = sCurrent.getRange(lastRow, 1, 1, 7).getValues()[0];
      return {
        timestamp: data[0],
        location: CONFIG.LOCATION.CITY,
        current: {
          temp_c: data[1],
          humidity: data[2],
          wind_speed_kmh: data[3],
          rain_mm: data[4],
          pressure_hpa: data[5],
          weather_desc: data[6],
          clouds_pct: 50,
          visibility_km: "10.0",
          wave_meter: 1.0
        },
        risk: WeatherAPI.evaluateEWS({ rain_mm: data[4], wind_speed_kmh: data[3], wave_meter: 1.0 }),
        forecast_7d: WeatherAPI.generateDailyForecast({ temp_c: data[1], wind_speed_kmh: data[3] }),
        hourly_forecast: [],
        is_cached: true
      };
    } catch (e) {
      return null;
    }
  },

  getAnalyticsHistory: function() {
    try {
      const ss = this.getDatabaseSpreadsheet();
      const sCurrent = ss.getSheetByName(CONFIG.DATABASE.SHEETS.CURRENT);
      const lastRow = sCurrent.getLastRow();
      if (lastRow <= 1) return [];

      const rowCount = Math.min(24, lastRow - 1);
      const values = sCurrent.getRange(lastRow - rowCount + 1, 1, rowCount, 7).getValues();

      return values.map(r => ({
        timestamp: r[0],
        temp: r[1],
        humidity: r[2],
        wind: r[3],
        rain: r[4]
      }));
    } catch (e) {
      return [];
    }
  }
};