var VOTE_LIST_FIRST_NAME_ROW = 15;

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
    var html = HtmlService
        .createTemplateFromFile('Panel')
        .evaluate()
        .setTitle('Hlasování')
        .setWidth(300);
    SpreadsheetApp.getUi()
        .showSidebar(html);
}

function getNamesSheet() {
    var sheet = SpreadsheetApp.getActiveSheet();
    if (!sheet.getSheetName().match(/^Hlasování/)) {
        throw new Error("Toto není tabulka hlasování");
    }

    return sheet;
}

function getNamesRange() {
    var sheet = getNamesSheet();

    var last = sheet.getLastRow();

    var range = sheet.getRange(VOTE_LIST_FIRST_NAME_ROW, 3, last - VOTE_LIST_FIRST_NAME_ROW + 1, 3);

    return range;
}

function getCurrentNames() {
    var range = getNamesRange();

    var values = range.getValues();

    //Hack for unique merged sorded objects
    var names = {};
    var order = [];
    for (y in values) {
        var name = values[y][0];
        var value = values[y][2];
        if (name === '') continue;

        var isNew = names[name] === undefined;
        var isFilled = value !== '';

        // Merge - set as filled when a least one item is filled
        names[name] = isFilled || (isNew ? false : names[name]);
        if (isNew) order.push(name);
    }

    order.sort();

    var output = [];
    for (i in order) {
        var name = order[i];
        output.push({
            'name': name,
            'filled': names[name]
        });
    }

    return output;
}

function vote(name, value) {
    var range = getNamesRange();
    var values = range.getValues();
    for (y in values) {
        if (values[y][0] == name) {
            range.getCell(parseInt(y) + 1, 3).setValue(value);
        }
    }
}

function voteRest(value) {
    var range = getNamesRange();
    var values = range.getValues();
    for (y in values) {
        if (values[y][2] == '') {
            range.getCell(parseInt(y) + 1, 3).setValue(value);
        }
    }
}

function clearAllVotes() {
    var range = getNamesRange();
    var values = range.getValues();
    for (y in values) {
        range.getCell(parseInt(y) + 1, 3).setValue(values[y][0] == '' ? 'Nepřítomen' : '');
    }
}

function clearAll() {
    sheet = getNamesSheet();
    clearAllVotes();
    sheet.getRange("A7").setValue('');
}

function createNewVoteSheet() {
    var sheet = getNamesSheet(); //only test to valid active sheet
    var name = sheet.getName();

    var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();

    sheet = spreadsheet.duplicateActiveSheet();

    var numMatch = name.match(/\d+/);
    if (numMatch) {
        numMatch = parseInt(numMatch[0]) + 1;
        name = name.replace(/\d+/, numMatch);
    }

    sheet.setName(name);
    sheet.getRange("A5").setValue(name);
    clearAll();
}