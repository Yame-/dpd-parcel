jQuery(document).ready(function($){

    var gMap = null;

    function initMap( map ) {
        gMap = new google.maps.Map(map, {
            zoom: 4
        });

        addDPDMarkers(gMap);
    }

    // Hide, and init map;
    //initMap( $('#map')[0] );

    $('body').on('click', '.openDPDParcelMap, .otherDPDParcel', function(e){
        e.preventDefault();

        $('#overlay').show();
        $('#map').show();
        initMap( $('#map')[0] );
    });

    $('body').on('click', '#overlay', function(){
        $('#overlay').hide();
    });

    $('body').on('click', '#map', function(e){
        e.stopPropagation();
    });

    $('body').on('click', '.selectDPDParcelradio', function(e){

        $('input[name="chosenParcelShopID"]').val( $(this).attr('data-parcelid') );
        $('input[name="chosenParcelShop_street"]').val( $(this).attr('data-street') );
        $('input[name="chosenParcelShop_city"]').val( $(this).attr('data-city') );
        $('input[name="chosenParcelShop_postcode"]').val( $(this).attr('data-postcode') );
        $('input[name="chosenParcelShop_name"]').val( $(this).attr('data-name') );

    });

    $('body').on('click', '.selectDPDParcel', function(e){
        e.preventDefault();

        $('input[name="chosenParcelShopID"]').val( $(this).attr('data-parcelid') );
        $('input[name="chosenParcelShop_street"]').val( $(this).attr('data-street') );
        $('input[name="chosenParcelShop_city"]').val( $(this).attr('data-city') );
        $('input[name="chosenParcelShop_postcode"]').val( $(this).attr('data-postcode') );
        $('input[name="chosenParcelShop_name"]').val( $(this).attr('data-name') );

        $('.openDPDParcelMap').hide();
        $('.otherDPDParcel').show();

        var parcel = $('#' + $(this).attr('data-parcelid')).clone();
        parcel.find('input').hide();
        $('.chosenParcel').html( parcel );

        $('#overlay').hide();
    });

})