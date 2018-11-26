document.getElementById('updateGames').addEventListener('click', function(evt) {
    http.get({
        url: '/api/v1/updateGamesImages',
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        window.alert('GOG Game Grab™ Started!');
    });
});

document.getElementById('updateImages').addEventListener('click', function(evt) {
    evt.preventDefault();
    http.get({
        url: '/api/v1/updateImages',
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        window.alert('Images Grab™ Started!');
    });
});

document.getElementById('addGameViaId').addEventListener('submit', function(evt) {
    evt.preventDefault();
    var form = new FormData(this);
    http.post({
        url: '/api/v1/addgamebasedonid',
        data: form,
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        var json = JSON.parse(res);
        if (json.SUCCESS) {
            window.alert(json.MSG);
        } else {
            window.alert('Something fucked up!');
        }
    });
});

document.getElementById('batch-file').addEventListener('change', function(evt) {
    var files = evt.target.files;
    var file = files[0];
    if (file) {
        var reader = new FileReader();
        reader.readAsText(file, "UTF-8");
        reader.onload = function (evt) {
            document.getElementById('batch-textarea').value = evt.target.result;
        };
        reader.onerror = function (evt) {
            document.getElementById('batch-textarea').value = "Error reading file.";
        };
    }
});

document.getElementById('batch-edit').addEventListener('submit', function(evt) {
    evt.preventDefault();
    var form = new FormData(this);
    http.post({
        url: '/api/v1/games/batchedit',
        data: form,
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        var json = JSON.parse(res);
        window.alert(json.MSG);
    });
});


function refreshLogs(){
    // Get Logs
    http.get({
        url: '/api/v1/getlog',
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        var json = JSON.parse(res);
        var str = '';
        for (var i = 0; i < json.length; i++) {
            var id = json[i].id;
            var value = json[i].value;
            var date = json[i].date;
            date = moment(date*1000).format('MMM Do YYYY, h:mm:ss A');
            str = str+'<option value="'+id+'">'+date+' - '+value+'</option>';
        }
        document.getElementById('log').innerHTML = str;
    });
}

setInterval(refreshLogs, 15000);
refreshLogs();

var limit = 15;
var showHidden = false;
var searchTerm = null;
var params = {};
var currentGet = null;
var sourceData = null;
var changedRows = {};
function getGames(){
    if (currentGet !== null) {
        currentGet.abort();
    }
    currentGet = http.get({
        url: '/api/v1/getgames',
        data: Object.assign({limit: limit, showHidden: showHidden, term: searchTerm}, params),
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        var data = JSON.parse(res);
        gameEdit.loadData(data.data);
        gameEdit.loadData(data.data);
        // Have to add twice cause Handsontable bug

        sourceData = JSON.parse(JSON.stringify(data.data));
        gameEditEl.classList.add('overflow');
        gameEdit.updateSettings({
            colHeaders: data.headers,
            width: gameEditEl.clientWidth
        });
    });
}

function saveGames(){
    http.post({
        url: '/api/v1/savegames',
        data: {
            data: JSON.stringify(changedRows)
        },
        headers: {
            'X-Api-Key': APIKEY
        }
    }, function(res){
        var data = JSON.parse(res);
        console.log(data);
        changedRows = {};
        document.querySelector('[data-changes-amount]').innerHTML = '0';
    });
}

Handsontable.renderers.registerRenderer('god.moment.unix', function(hotInstance, td, row, column, prop, value, cellProperties){
    if (parseInt(value) > 0) {
        td.innerHTML = moment.unix(value).format('llll');
    } else {
         td.innerHTML = '<i>null</i>';
    }
    return td;
});

Handsontable.renderers.registerRenderer('god.bool', function(hotInstance, td, row, column, prop, value, cellProperties){
    if (value == '0') {
        td.innerHTML = 'false';
    } else if (value == '1'){
        td.innerHTML = 'true';
    }
    return td;
});

document.getElementById('saveRows').addEventListener('click', saveGames);

var colBool = { type: 'dropdown', source: ['true', 'false'], sortIndicator: true };
var colDateTime = {
            type: 'date',
            renderer: 'god.moment.unix',
            sortIndicator: true,
            correctFormat: true,
            dateFormat: 'X',
            datePickerConfig: {
                yearRange: [1950, moment().year()+1],
                showSeconds: false,
                use24hour: false
            }
        };

var gameEditEl = document.getElementById('game-edit-table');
var gameEdit = new Handsontable(gameEditEl, {
    rowHeaders: false,
    afterInit: getGames,
    columnSorting: true,
    manualColumnResize: true,
    afterColumnSort: function(colIndex, sorted){
        if (typeof(gameEdit.getSettings().columns[colIndex].sortIndicator) === 'boolean'){
            var colName = gameEdit.getColHeader(colIndex);
            if (typeof(sorted) === 'undefined') {
                params = {};
                getGames();
                return;
            }
            params = {sort: colName, sortOrder: sorted};
            getGames();
        }
        // Can't figure out how to stop a Handsontable event properly
        throw "not actually an exception";
    },
    afterChange: function (change, source) {
        if (source === 'loadData') {
            return;
        }
        if (source === 'edit' || source === 'Autofill.fill') {
            for (var i = change.length - 1; i >= 0; i--) {
                // check if actually changed
                var c = change[i];
                var gameID = parseInt(gameEdit.getSourceDataAtRow(c[0])[0]);
                var columnName = gameEdit.getColHeader(c[1]);
                var sourceRow = sourceData[c[0]];
                var sourceVal = sourceRow[c[1]];
                var newRow = gameEdit.getSourceDataAtRow(c[0]);
                var newVal = c[3];
                if (JSON.stringify(sourceRow) !== JSON.stringify(newRow)) {
                    changedRows[columnName+'_'+gameID] = {
                        id: gameID,
                        old: sourceVal,
                        new: newVal,
                        column: gameEdit.getColHeader(c[1])
                    };
                } else {
                    if (changedRows[columnName+'_'+gameID] !== undefined) {
                        delete changedRows[columnName+'_'+gameID];
                    }
                }
            }
        }
        document.querySelector('[data-changes-amount]').innerHTML = Object.keys(changedRows).length;
    },
    columns: [
        {
            readOnly: true,
            sortIndicator: true
        },
        {
            sortIndicator: true
        },
        colBool,
        colBool,
        colBool,
        colBool,
        colBool,
        colBool,
        colDateTime,
        colDateTime,
        colBool,
        colBool,
        {
            sortIndicator: true
        },
        {
            sortIndicator: true
        },
        {
            sortIndicator: true
        },
        colDateTime,
        {
            sortIndicator: true
        },
        {
            sortIndicator: true
        },
        {
            sortIndicator: true
        }
    ]
});

var gameEditOptEl = document.getElementById('game-edit-options');

var updateSettings = debounce(function(evt) {
    evt.preventDefault();
    var form = new FormData(this);

    // set global variables
    limit = form.get('limit');

    if (form.get('showHidden') == 'on') {
        showHidden = true;
    } else {
        showHidden = false;
    }

    searchTerm = form.get('search');

    getGames();
}, 200);

gameEditOptEl.addEventListener('change', updateSettings);
gameEditOptEl.addEventListener('keyup', updateSettings);