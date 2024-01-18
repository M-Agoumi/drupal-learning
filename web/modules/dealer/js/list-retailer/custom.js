jQuery(document , drupalSettings).ready(function($) {
  var pathname = window.location.pathname.split("/");
  var userId = pathname[pathname.length-1];

  var dashboardUrl = drupalSettings.dashboardUrl;
  dashboardUrl = dashboardUrl + '?id_revendeur=' + userId;

  var settings = {
    "async": true,
    "crossDomain": true,
    "url": dashboardUrl,
    "method": "GET"
  };
  var formattedResponse;
  $.ajax(settings).done(function (response) {

    formattedResponse = window.JSON.parse(response);

    /*
        Dashboard Charts
     */
    Highcharts.chart('statistiqueContainer', {
      chart: {
        type: 'column'
      },
      title: {
        text: 'Statistique'
      },
      subtitle: {
        text: 'Statistiques ' + formattedResponse['categories']
      },
      xAxis: {
        categories: formattedResponse['categories'],
        crosshair: true
      },
      yAxis: {
        min: 0,
        title: {
          text: 'valeur (dh)'
        }
      },
      credits: {
        enabled: false
      },
      tooltip: {
        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
        pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
        '<td style="padding:0"><b>{point.y:.1f} dh</b></td></tr>',
        footerFormat: '</table>',
        shared: true,
        useHTML: true
      },
      plotOptions: {
        column: {
          pointPadding: 0.2,
          borderWidth: 0
        }
      },
      /*series: [{
        name: "Achat Facture",
        data: [499.9, 791.5, 1096.4, 1299.2, 1494.0, 1796.0, 1359.6, 1498.5, 2916.4, 1994.1, 995.6, 549.4]

      }, {
        name: "Vente Facture",
        data: [49.9, 711.5, 196.4, 1099.2, 1094.0, 1299.2, 1494.0, 498.5, 916.4, 1124.1, 295.6, 289.4]

      }, {
        name: "Achat Recharge",
        data: [499.9, 791.5, 1096.4, 1299.2, 1494.0, 179.0, 139.6, 498.5, 916.4, 1124.1, 295.6, 289.4]

      }, {
        name: "Vente Recharge",
        data: [1124.1, 295.6, 289.4, 1096.4, 1299.2, 1494.0, 1359.6, 1498.5, 2916.4, 179.0, 139.6, 498.5]

      }]*/
      series: formattedResponse['series']
    });
  });


  /*
    Nav - Liste des retailers - Statistique - style
   */
  $('.nav-retailers').css('background-color','lightgray');

  $('.nav-retailers').on('click', function () {
    $('.nav-retailers').css('background-color','lightgray');
    $('.nav-stats').css('background-color','white');
    $('.nav-operations').css('background-color','white');
  });
  $('.nav-stats').on('click', function () {
    $('.nav-stats').css('background-color','lightgray');
    $('.nav-retailers').css('background-color','white');
    $('.nav-operations').css('background-color','white');
  });
  $('.nav-operations').on('click', function () {
    $('.nav-operations').css('background-color','lightgray');
    $('.nav-retailers').css('background-color','white');
    $('.nav-stats').css('background-color','white');
  });

} );


