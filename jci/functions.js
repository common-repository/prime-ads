//AdBlock Detect
(function(root, factory) {
    /* istanbul ignore next */
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.adblockDetect = factory();
    }

}(this, function() {
    function adblockDetect(callback, options) {
        options = merge(adblockDetect.defaults, options || {});

        var testNode = createNode(options.testNodeClasses, options.testNodeStyle);
        var runsCounter = 0;
        var adblockDetected = false;

        var testInterval = setInterval(function() {

            runsCounter++;
            adblockDetected = isNodeBlocked(testNode);

            if (adblockDetected || runsCounter === options.testRuns) {
                clearInterval(testInterval);
                testNode.parentNode && testNode.parentNode.removeChild(testNode);
                callback(adblockDetected);
            }
        }, options.testInterval);
    }

    function createNode(testNodeClasses, testNodeStyle) {
        var document = window.document;
        var testNode = document.createElement('div');

        testNode.innerHTML = '&nbsp;';
        testNode.setAttribute('class', testNodeClasses);
        testNode.setAttribute('style', testNodeStyle);

        document.body.appendChild(testNode);

        return testNode;
    }

    function isNodeBlocked(testNode) {
        return testNode.offsetHeight === 0 ||
            !document.body.contains(testNode) ||
            testNode.style.display === 'none' ||
            testNode.style.visibility === 'hidden'
        ;
    }

    function merge(defaults, options) {
        var obj = {};

        for (var key in defaults) {
            obj[key] = defaults[key];
            options.hasOwnProperty(key) && (obj[key] = options[key]);
        }

        return obj;
    }

    adblockDetect.defaults = {
        testNodeClasses: 'pub_300x250 pub_300x250m pub_728x90 text-ad textAd text_ad text_ads text-ads text-ad-links',
        testNodeStyle: 'height: 10px !important; font-size: 20px; color: transparent; position: absolute; bottom: 0; left: -10000px;',
        testInterval: 51,
        testRuns: 4
    };

    return adblockDetect;
}));
// end AdblockDetect

var closed_blocks = Cookies.get('closed_blocks');
if(!closed_blocks) closed_blocks = [];

var loaderStarted = false;

function linkCount(obj){
    jQuery.ajax({
        type: 'POST',
        url: prma_ajax_object.prma_ajax_url,
        data: {
            action: 'prma_regclick',
            rel: obj.data('rel'),
            cache: false,
        },
        dataType: 'json'
    });
}
function regClose(obj){
    closed_blocks.push(obj.parent().data('id'));
    Cookies.set('closed_blocks', closed_blocks);
}
function counterTick(type){
    jQuery.ajax({
        type: 'POST',
        url: prma_ajax_object.prma_ajax_url,
        data: {
            action: 'prma_counter_tick',
            type: type,
            cache: false,
        },
        dataType: 'json'
    });
}
var prma_window_width;
function checkMobileBlockHeight(){
    jQuery('.prma-mobile-block').each(function(index, el) {
       if(jQuery(this).height() > jQuery(window).height()*0.2) jQuery(this).remove(); 
    });
}
function linkOut(e){
    window.open(e.data.to);
}
jQuery(document).ready(function($) {
    prma_window_width = $(window).width();
    checkMobileBlockHeight();
    $('body').on('mouseup', '.prma-close', function(event) {
        regClose($(this));
    });
    $('.prma-google-block').each(function(index, el) {
        var $this = $(el);
        $this.find('iframe').iframeTracker({
            blurCallback: function(){
                linkCount($this);
            }
        });
    });
    var positions = [];
    $('.prma-position-pointer').each(function(index, el) {
        positions.push($(el).data('id'));
    });

    if(positions.length > 0){
        adblockDetect(function(adblockDetected) {
            loadBlocks(adblockDetected);
        });

        // если не сработала загзрузка после определения адблока
        setTimeout(function(){
            if(!loaderStarted) loadBlocks(false);
        }, 1000);
    }

    function loadBlocks(adblockDetected){
        if(loaderStarted) return;
        loaderStarted = true;
        $.ajax({
            type: 'POST',
            url: prma_ajax_object.ajax_url,
            data: {
                action: 'prma_load_positions',
                id: positions,
                width: $(window).width(),
                adblockDetected: adblockDetected ? 1 : 0,
                cache: false,
            },
            success: function (data) { 
                $.each(positions, function(index, val) {
                    var el = $('.prma-position-pointer[data-id='+val+']');
                    var ins = $(data[val].position_data);
                    if(el.data('place') == 12 || el.data('place') == 15){ // за картинкой - подгружаем содержимое блока
                        if(data[val].position_data == '')
                            el.html(el.find('.front').html());
                        else
                            el.find('.back').html(ins);
                    }else{
                        el.after(ins);
                        el.remove();
                    }
                    if(data[val].position_js != '' && data[val].position_js != null){
                        $('<script />').attr("type", "text/javascript").html(data[val].position_js).appendTo('body');
                    }
                });           
                checkMobileBlockHeight();
            },
            dataType: 'json'
        });
    }
});