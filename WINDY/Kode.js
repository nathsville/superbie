/**
 * Code.gs - Web App Entry Point
 */
function doGet(e) {
  const template = HtmlService.createTemplateFromFile("index");
  
  // Ambil data cuaca langsung di sisi server
  let initialData;
  try {
    initialData = WeatherAPI.getComprehensiveWeather();
  } catch (err) {
    initialData = WeatherAPI.generateFallbackData();
  }
  
  // Kirim data ke HTML template
  template.initialDataJson = JSON.stringify(initialData);

  return template.evaluate()
    .setTitle("Parepare Weather Intelligence Dashboard")
    .addMetaTag("viewport", "width=device-width, initial-scale=1.0")
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function include(filename) {
  return HtmlService.createHtmlOutputFromFile(filename).getContent();
}