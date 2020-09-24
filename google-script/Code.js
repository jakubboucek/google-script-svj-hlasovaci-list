const VOTE_LIST_FIRST_NAME_ROW = 15;

function clearOldVotes() {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    const sheets = spreadsheet.getSheets();

    sheets.shift(); // List
    sheets.shift(); // Hlas 1

    for (const sheet of sheets) {
        spreadsheet.deleteSheet(sheet);
    }

    spreadsheet.getSheetByName("Hlasování č. 1").activate();
    clearSheetInludePersons();
}

function onOpen() {
    SpreadsheetApp.getUi()
        .createMenu('Hlasovací nástoje')
        .addItem('Panel hlasování', 'showSidebar')
        .addToUi();
}

function include(filename) {
    return HtmlService.createHtmlOutputFromFile(filename)
        .getContent();
}

function showSidebar() {
    const html = HtmlService
        .createTemplateFromFile('Panel')
        .evaluate()
        .setTitle('Hlasování')
        .setWidth(300);
    SpreadsheetApp.getUi()
        .showSidebar(html);
}

function getNamesSheet() {
    const sheet = SpreadsheetApp.getActiveSheet();
    if (!sheet.getSheetName().match(/^Hlasování/)) {
        throw new Error("Toto není tabulka hlasování");
    }

    return sheet;
}

function getNamesRange() {
    const sheet = getNamesSheet();

    const last = sheet.getLastRow();

    return sheet.getRange(VOTE_LIST_FIRST_NAME_ROW, 3, last - VOTE_LIST_FIRST_NAME_ROW + 1, 3);
}

function getCurrentNames() {
    const range = getNamesRange();

    const values = range.getValues();

    //Hack for unique merged sorded objects
    const names = {};
    const order = [];
    for (const y of values) {
        const name = y[0];
        const value = y[2];
        if (name === '') continue;

        const isNew = names[name] === undefined;
        const isFilled = value !== '';

        // Merge - set as filled when a least one item is filled
        names[name] = isFilled || (isNew ? false : names[name]);
        if (isNew) order.push(name);
    }

    order.sort();

    const output = [];
    for (const name of order) {
        output.push({
            'name': name,
            'filled': names[name]
        });
    }

    return output;
}

function vote(name, value) {
    const range = getNamesRange();
    const values = range.getValues();
    for (const y in values) {
        if (values.hasOwnProperty(y) === false) continue;
        if (values[y][0] === name) {
            range.getCell(parseInt(y) + 1, 3).setValue(value);
        }
    }
}

function voteRest(value) {
    const range = getNamesRange();
    const values = range.getValues();
    for (const y in values) {
        if (values.hasOwnProperty(y) === false) continue;
        if (values[y][2] === '') {
            range.getCell(parseInt(y) + 1, 3).setValue(value);
        }
    }
}

function clearAllVotes() {
    const range = getNamesRange();
    const values = range.getValues();
    for (const y in values) {
        if (values.hasOwnProperty(y) === false) continue;
        range.getCell(parseInt(y) + 1, 3).setValue(values[y][0] === '' ? 'Nepřítomen' : '');
    }
}

function clearAll() {
    const sheet = getNamesSheet();
    clearAllVotes();
    sheet.getRange("A7").setValue('');
}

function clearSheetInludePersons() {
    const sheet = getNamesSheet();
    const namesRange = getNamesRange();

    const values = namesRange.getValues();
    for (const value of values) {
        value[0] = '';
    }
    namesRange.setValues(values);

    clearAllVotes();
}

function createNewVoteSheet() {
    let sheet = getNamesSheet(); //only test to valid active sheet
    let name = sheet.getName();

    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();

    sheet = spreadsheet.duplicateActiveSheet();

    let numMatch = name.match(/\d+/);
    if (numMatch) {
        numMatch = parseInt(numMatch[0]) + 1;
        name = name.replace(/\d+/, numMatch);
    }

    sheet.setName(name);
    sheet.getRange("A5").setValue(name);
    clearAll();
}