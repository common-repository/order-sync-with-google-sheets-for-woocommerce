const baseURL = "{site_url}",
  accessToken = "{token}",
  order_statuses = "{order_statuses}",
  sheetTab = "{sheet_tab}";

  function RunOSGSW() {
    if (!osgsw_current_sheet()) return;
    osgsw_add_menus();
    osgsw_apply_styles();
}

function onOpen() {
    RunOSGSW();
}

function osgsw_current_sheet() {
    return SpreadsheetApp.getActiveSheet().getSheetName() === sheetTab;
}

function onChange() {
    if (!osgsw_current_sheet() || osgsw_current_row() == 1) return;
    const currentColumn = osgsw_current_column();
    let data = osgsw_get_edited_data2();
    var osgs_ui = SpreadsheetApp.getUi();
    if (currentColumn !== "order_status") {
        osgs_ui.alert("This column is not editable");
        osgsw_fetch_from_WordPress();
        return;
    }
    Logger.log("Current Column " + currentColumn);
    let message = osgsw_columns(currentColumn, true) + " updated successfully!";
    if (data[0].order_Id == "") {
        return;
    }
    osgsw_apply_styles();
    osgsw_sync_data(data, message);
}


function osgsw_get_edited_data2() {
    let row = SpreadsheetApp.getActive().getSheetByName(sheetTab).getCurrentCell().getRow();
    let values = SpreadsheetApp.getActive().getSheetByName(sheetTab).getRange(row, 1, 1, SpreadsheetApp.getActive().getSheetByName(sheetTab).getLastColumn()).getValues();
    values = values[0];
    values.map((col, index) => {
        if (index === 0) return parseInt(col);
        return col;
    });
    return osgsw_format([values]);
}

function onEdit(e) {
    if (e == null || e == 'undefined' || e == '') return;
    if (e.triggerUid == null) return;
    if (!osgsw_current_sheet() || osgsw_current_row() == 1) return;
    const currentColumn = osgsw_current_column();
    var osgs_ui = SpreadsheetApp.getUi();
    if (currentColumn !== "order_status") {
        osgs_ui.alert("This column is not editable");
        osgsw_fetch_from_WordPress();
        return;
    }
    osgsw_toast2('Updating data... Please wait.', 'Loading!');
    let data = osgsw_get_edited_data(e);
    Logger.log(data);
    let key_value = osgsw_columns(currentColumn, true);
    if (typeof key_value === "undefined") {
        key_value = currentColumn;
    }
    let message = key_value + " updated successfully!";
    if (data[0].order_Id == "") {
        osgs_ui.alert("This column is not editable");
        osgsw_fetch_from_WordPress();
        return;
    }
    osgsw_apply_styles();
    osgsw_sync_data(data, message);
}

function osgsw_toast2(message = null, title = null) {
    SpreadsheetApp.getActiveSpreadsheet().toast(message, title, -1);
}

function osgsw_add_menus() {
    SpreadsheetApp.getUi().createMenu("Order Sync").addItem("⟱ Fetch Order from WooCommerce", "osgsw_fetch_from_WordPress").addSeparator().addItem(" Format Styles", "osgsw_apply_styles").addItem(" About Order Sync", "osgsw_about_us").addToUi();
}

function osgsw_apply_styles() {
    const StaticColumns = osgsw_column_index(["order_date", "order_Id", "shipping_information", "product_names", "payment_method", "customer_note", "order_url", "order_quantity", "order_totals", "discount_total", "billing_details", "order_placed_by", ]).filter((index) => index >= 0).map(osgsw_column_char);
    const OrderColumn = osgsw_column_index(["order_status"]).map((index) => osgsw_column_char(index))[0];
    const StaticColumnHeaders = StaticColumns.map((column) => column + 1);
    const StaticColumnValues = StaticColumns.map((column) => column + 2 + ":" + column);
    const OrderColumnValues = OrderColumn + 2 + ":" + OrderColumn;
    const CenterableColumns = osgsw_column_index(["order_status"]).filter((index) => index >= 0).map((char) => osgsw_column_char(char) + "1:" + osgsw_column_char(char));
    const Color = {
        primary: "#686de0",
        white: "white",
        black: "black",
        grey: "#dedede",
        success: "green",
        error: "indianred",
        info: "purple",
        warning: "orange",
    };
    const dynamicColumnRange = osgsw_getDynamicColumnRange(StaticColumnHeaders.length);
    const dynamic_values = dynamicColumnRange[0];
    const dynamic_header = dynamicColumnRange[1];
    const CurrentSheet = SpreadsheetApp.getActive().getSheetByName(sheetTab);
    CurrentSheet.getRangeList(['C1']).setFontWeight("bold").setBackground(Color.primary).setFontColor(Color.white);
    CurrentSheet.autoResizeColumns(1, osgsw_max_column());
    Logger.log(StaticColumnHeaders);
    CurrentSheet.getRangeList(StaticColumnHeaders).setBackground(Color.error).setFontWeight("bold");
    if (dynamic_values.length > 0) {
        CurrentSheet.getRangeList(dynamic_values).setBackground(Color.grey).setFontColor(Color.black).setFontWeight("normal");
        CurrentSheet.getRangeList(dynamic_header).setBackground(Color.error).setFontWeight("bold");
    }
    CurrentSheet.getRangeList(['C2:C']).setBackground(Color.white);
    CurrentSheet.getRangeList(StaticColumnValues).setBackground(Color.grey).setFontColor(Color.black).setFontWeight("normal");;
    CurrentSheet.getRangeList(CenterableColumns).setHorizontalAlignment("left");
    let rules = [];
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-completed").setBackground("#f7fff9").setFontColor("green").setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-failed").setBackground("#fff8f7").setFontColor(Color.error).setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("'wc-refunded'").setBackground("#fff8f7").setFontColor(Color.error).setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-checkout-draft").setBackground("#fff8f7").setFontColor(Color.error).setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-cancelled").setBackground("#fff8f7").setFontColor(Color.error).setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-processing").setBackground("#fffdf7").setFontColor("orange").setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-pending").setBackground("#fffdf7").setFontColor("orange").setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenTextEqualTo("wc-on-hold").setBackground("#fffdf7").setFontColor("orange").setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenNumberGreaterThan(0).setBackground("#f7fff9").setFontColor(Color.success).setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenNumberLessThan(1).setBackground("#fff8f7").setFontColor(Color.error).setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    rules.push(SpreadsheetApp.newConditionalFormatRule().whenFormulaSatisfied('=LEFT(C2, 3) = "wc-"').setBackground("#fffdf7").setFontColor("orange").setRanges([SpreadsheetApp.getActiveSheet().getRange(OrderColumnValues)]).build());
    CurrentSheet.setConditionalFormatRules(rules);
    Logger.log("Order Sync Installed!");
}

function osgsw_about_us() {
    let htmlOutput = HtmlService.createHtmlOutput(`<h3>Order Sync with Google Sheet for WooCommerce</h3> <p>Sync your WooCommerce Order with Google Sheets.</p> <p><a href="https://wordpress.org/plugins/order-sync-with-google-sheets-for-woocommerce/" target="_blank">Download Free</a> version from WordPress.org</p> <p><a href="https://wcordersync.com/pricing/" target="_blank">Get Ultimate</a> version to enjoy all premium features and official updates.</p> `).setWidth(550).setHeight(200);
    SpreadsheetApp.getUi().showModalDialog(htmlOutput, "Order Sync with Google Sheet for WooCommerce");
}

function osgsw_headers() {
    let header = SpreadsheetApp.getActive().getSheetByName(sheetTab).getRange("A1:Z1").getValues();
    header = header[0].filter((column) => column.length);
    return header;
}

function osgsw_columns($key = null, $reversed = false) {
    let columns = {
        "Order ID": "order_Id",
        "Product Names": "product_names",
        "Order Status": "order_status",
        "Total Items": "order_quantity",
        "Total Price": "order_totals",
        "Total Discount": "discount_total",
        "Billing Details": "billing_details",
        "Order Date": "order_date",
        "Shipping Details": "shipping_information",
        "Payment Method": "payment_method",
        "Customer Note": "customer_note",
        "Order Placed by": "order_placed_by",
        "Order URL": "order_url",
    };
    if ($key) {
        if (!$reversed) {
            return columns[$key];
        } else {
            let reverse = {};
            for (let key in columns) {
                reverse[columns[key]] = key;
            }
            return reverse[$key];
        }
    }
    return columns;
}

function osgsw_available_columns() {
    let columns = osgsw_columns();
    let headers = osgsw_headers();
    let keys = {};
    headers.forEach((header) => {
        if (Object.keys(columns).includes(header)) {
            keys[header] = columns[header];
        }
    });
    return keys;
}

function osgsw_max_column() {
    let maxColumn = SpreadsheetApp.getActive().getSheetByName(sheetTab).getLastColumn();
    return maxColumn;
}

function osgsw_column_char(index = 0) {
    const alphabet = "abcdefghijklmnopqrstuvwxyz".toUpperCase().split("");
    if (index > 26) {
        return getA1NotationForColumn(index);
    } else {
        return alphabet[index - 1] || null;
    }
}

function osgsw_current_row() {
    let currentCell = SpreadsheetApp.getActive().getSheetByName(sheetTab).getCurrentCell().getA1Notation();
    let row = currentCell.replace(/[^0-9]/g, "");
    return row;
}

function osgsw_current_column() {
    let currentCell = SpreadsheetApp.getActive().getSheetByName(sheetTab).getCurrentCell().getA1Notation();
    let rowNotation = currentCell.replace(/[0-9]/g, "");
    rowNotation = "abcdefghijklmnopqrstuvwxyz".toUpperCase().split("").indexOf(rowNotation);
    let column = Object.values(osgsw_available_columns())[rowNotation];
    return column;
}

function osgsw_column_index(columns) {
    let indexes = [];
    let available_columns = osgsw_available_columns();
    columns.forEach((column) => {
        let index = Object.values(available_columns).indexOf(column);
        if (index >= 0) index++;
        indexes.push(index);
    });
    return indexes;
}

function osgsw_format(data) {
    const deletables = ["order_url"];
    const keys = osgsw_ordered_keys();
    data = data.map((row) => {
        return Object.assign.apply({}, keys.map((v, i) => ({
            [v]: row[i],
        })));
    }).map((row) => {
        deletables.forEach((key) => {
            if (key in row) delete row[key];
        });
        return row;
    });
    return data;
}

function osgsw_new_getColumnLetter(columnIndex) {
  var columnLetter = '';
  while (columnIndex > 0) {
    var modulo = (columnIndex - 1) % 26;
    columnLetter = String.fromCharCode(modulo + 'A'.charCodeAt(0)) + columnLetter;
    columnIndex = Math.floor((columnIndex - modulo) / 26);
  }
  return columnLetter;
}

function osgsw_getDynamicColumnRange(staticColumnsEndIndex) {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getActiveSheet();
    const startColumnIndex = staticColumnsEndIndex + 1;
    const startColumn = startColumnIndex;
    const endColumn = sheet.getLastColumn();
    var columns = [];
    var column_header = [];

    for (var col = startColumn; col <= endColumn; col++) {
      var columnLetter = osgsw_new_getColumnLetter(col);
      column_header.push(columnLetter + '1');
      columns.push(columnLetter + '2:' + columnLetter);
    }

  return [columns,column_header];
}

function getA1NotationForColumn(columnIndex) {
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    const cell = sheet.getRange(1, columnIndex);
    return cell.getA1Notation();
}

function osgsw_getDynamicColumnIndex() {
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var endColumn = sheet.getLastColumn();
    const cell = sheet.getRange(1, endColumn);
    const enda1Notation = cell.getA1Notation();
    const alphabetPart = enda1Notation.match(/[A-Z]+/i)[0];
    return alphabetPart;
}

function osgsw_get_all_data() {
    var values = SpreadsheetApp.getActive().getSheetByName(sheetTab).getDataRange().getValues();
    values.shift();
    return osgsw_format(values);
}

function osgsw_get_edited_data(e) {
    var sheet = e.source.getSheetByName(sheetTab);
    var rowStart = e.range.rowStart;
    var rowEnd = e.range.rowEnd;
    var get_all_range = sheet.getRange(rowStart, 1, rowEnd - rowStart + 1, sheet.getLastColumn());
    var get_values = get_all_range.getValues();
    return osgsw_format2(get_values, rowStart);
}

function osgsw_format2(data, rowStart) {
    const deletabless = ["order_url"];
    const keyss = osgsw_ordered_keys();
    return data.map((row, index) => {
        let formattedRow = {};
        formattedRow["index_number"] = rowStart + index;
        keyss.forEach((key, i) => {
            formattedRow[key] = row[i];
        });
        deletabless.forEach((key) => {
            if (key in formattedRow) delete formattedRow[key];
        });
        return formattedRow;
    });
}

function osgsw_ordered_keys() {
    let orderedKeys = [];
    osgsw_headers().forEach((header) => {
        orderedKeys.push(osgsw_available_columns()[header]);
    });
    return orderedKeys;
}

function osgsw_sync_all() {
    let data = osgsw_get_all_data();
    osgsw_sync_data(data);
}

function osgsw_toast(message = null, title = null) {
    SpreadsheetApp.getActiveSpreadsheet().toast(message, title);
}

function osgsw_sync_data(data, message = "Orders synced successfully") {
    let orders = data.filter((row) => {
        return row[2] !== "" && row[0] !== "order_Id";
    });
    if (!orders.length) return;
    let response = UrlFetchApp.fetch(baseURL + "/wp-json/osgsw/v1/update", {
        method: "POST",
        payload: JSON.stringify({
            orders,
            message,
        }),
        contentType: "application/json",
        muteHttpExceptions: true,
        headers: {
            OSGSWKEY: "Bearer " + accessToken,
        },
        timeout: 300,
    });
    if (response.getResponseCode() == 200) {
        response = JSON.parse(response.getContentText());
        if (response.success) {
            osgsw_toast(message, "Success!");
        } else if (response.message) {
            osgsw_toast(response.message, "Ops error!");
        }
    } else {
        osgsw_toast('Authentication Failed: REST API is not supported on your system', "Ops!");
    }
}

function osgsw_fetch_from_WordPress() {
    let response = UrlFetchApp.fetch(baseURL + "/wp-json/osgsw/v1/action/?action=sync", {
        method: "GET",
        contentType: "application/json",
        muteHttpExceptions: true,
        headers: {
            Authorization: "Bearer " + accessToken,
        },
    });
    if (response.getResponseCode() == 200) {
        response = JSON.parse(response.getContentText());
        if (response.success) {
            osgsw_toast("Orders fetched from WordPress", "Success!");
        } else if (response.message) {
            osgsw_toast(response.message, "Ops!");
        }
    } else {
        osgsw_toast('Authentication Failed: REST API is not supported on your system', "Ops!");
    }
}