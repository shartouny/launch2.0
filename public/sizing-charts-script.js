console.log('Sizing Chart Feature Will Be Released Soon!')
throw new Error("!! Stop Sizing Chart Script !!");



console.log('sizing-chart-script is loaded');

let pagePath = window.location.origin + window.location.pathname
let storeUrl = window.location.origin;

if(pagePath.includes('/products/')){
    let productDataUrl = pagePath + '.json';
    console.log('Extracting product data');
    parseProductData(productDataUrl);
}

function parseProductData(url) {
    let xhr;

    // code for IE7+, Firefox, Chrome, Opera, Safari
    if (window.XMLHttpRequest) {
        xhr = new XMLHttpRequest();
    }
    // code for IE6, IE5
    else {
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
    }

    xhr.onreadystatechange = function() {
        if (xhr.readyState==4 && xhr.status==200) {
            let data = JSON.parse(xhr.responseText);
            console.log('product id:', data.product.id);
            fetchProductSizingChart(data.product.id);
        }
    }

    xhr.open("GET", url, false );
    xhr.send();
}

function fetchProductSizingChart(productId) {
    let xhr;

    let url = 'https://app.teelaunch.com/api/v1/shopify/product-sizing-chart/'+productId+'?storeUrl='+storeUrl;
    // code for IE7+, Firefox, Chrome, Opera, Safari
    if (window.XMLHttpRequest) {
        xhr = new XMLHttpRequest();
    }
    // code for IE6, IE5
    else {
        xhr = new ActiveXObject("Microsoft.XMLHTTP");
    }

    xhr.onreadystatechange = function() {
        if (xhr.readyState==4 && xhr.status==200) {
            let data = JSON.parse(xhr.responseText);
            if(data.length > 0){
                addSizingChartDesign(data);
            }
        }
    }

    xhr.open("GET", url, false );
    xhr.send();
}

function addSizingChartDesign(data){
    let buffer = '';

    buffer += '<a class="teelaunch-sizing-chart-action" href="javascript:;" onclick="teelaunchSizingChartTrigger()">View Sizing Chart</a>'

    buffer += '<div class="teelaunch-sizing-chart" style="display: none">'
    for (var i = 0; i < data.length; i++){
        buffer += prepareChartBlock(data[i]);
    }
    buffer += '</div>'

    document.getElementsByClassName('shopify-payment-button')[0].innerHTML += buffer;
}

function prepareChartBlock(data){

    let buffer = '';

    buffer += '<table>';
    buffer += '<tbody>';

        buffer += '<tr>';
            buffer += '<th>';
                buffer += data.name;
            buffer += '</th>';

            for (var headersIndex = 0; headersIndex < data.headers.length; headersIndex++){
                if(data.headers[headersIndex]){
                    buffer += '<th>';
                    buffer += data.headers[headersIndex];
                    buffer += '</th>';
                }
            }
        buffer += '</tr>';

            for (var optionsIndex = 0; optionsIndex < data.options.length; optionsIndex++){
                buffer += '<tr>';

                    buffer += '<td>';
                        buffer += data.options[optionsIndex].name;
                    buffer += '</td>';

                for (var headersIndex = 0; headersIndex < data.headers.length; headersIndex++){
                    if(data.headers[headersIndex]){
                        buffer += '<td>';
                        for (var optionValuesIndex = 0; optionValuesIndex < data.optionValues.length; optionValuesIndex++){
                          if(data.optionValues[optionValuesIndex].rows[optionsIndex] && data.optionValues[optionValuesIndex].rows[optionsIndex]['column_'+ (headersIndex+1) +'_value'] != null){
                            buffer += data.optionValues[optionValuesIndex].abbreviation +': '+data.optionValues[optionValuesIndex].rows[optionsIndex]['column_'+ (headersIndex+1) +'_value'];
                            buffer += '<br>';
                          }
                        }
                        buffer += '</td>';
                    }
                }

                buffer += '</tr>';
            }

    buffer += '</tbody>';
    buffer += '</table>';
    buffer += '<br>';

    return buffer;
}

function teelaunchSizingChartTrigger(){
    let display = document.getElementsByClassName('teelaunch-sizing-chart')[0].style.display;

    if(display === 'block'){
        document.getElementsByClassName('teelaunch-sizing-chart')[0].style.display = 'none';
    }
    else{
        document.getElementsByClassName('teelaunch-sizing-chart')[0].style.display = 'block';
    }
}
