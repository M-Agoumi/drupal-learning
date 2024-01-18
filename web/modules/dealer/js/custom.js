jQuery(document , drupalSettings).ready(function($) {

  var urlNotification = drupalSettings.notificationUrl;
  /*
    datatable,
   */
  var tableVersion = $('#list_version').DataTable();

  var table = $('#list_dealer').DataTable();
  var tableOperations = $('#list_operations').DataTable();
  $('#list_notification').DataTable({
    "order": [[ 3, "desc" ]]
  });


  /*
      cacher "Statut", "✓ Validé", "✓ Refusé" si le revendeur n'est pas validé
      griser boutton accepter revendeur
   */
  if ($('.vendValide').is(":hidden")){
    $('.accepterRevendeurClicked').hide();
    $('.accepterRevendeur').prop('disabled', true);
  }

  /*
    Afficher le bouton d'activation du revendeur si le nombre de retailers validés >= limit
   */
    showButtonsIfApprouved();


  /*
      active jquery after pages change
   */
  $('.paginate_button, [name="list_dealer_length"], th, a, button').click(function (event) {

      // hide activé et decliné des retailers
      $('.activated').hide();
      $('.declined').hide();

      /*
          listener Bouton Activer retailer
       */
      $('.avtiveDealer').click(function (event) {

        event.preventDefault();

        calledUrlAvtive = $(this).data("url");
        $this = $(this);
        var retailerData = $(this).data("retailer");

        /*
          Get list revendeur of duplicated retailer
         */
        var stringDuplicated = "";

        calledUrl = $(this).data("duplicated");

        /*
        If retailer is duplicated, call Duplicated WS to get list revendeur,
         */
        if(calledUrl){
          $.ajax({
            url: calledUrl,
            type: "POST",
            data: {type:"getform"},
            error: function (request, status, error) {},
            complete: function () {},
            statusCode: {
              //traitement en cas de succès
              200: function (stringDuplicated) {
                swal({
                  title: 'Verification',
                  text: stringDuplicated + "Voulez-vous vraiment activer ce retailer pour ce revendeur ?",
                  type: 'success',
                  showCancelButton: true,
                  confirmButtonColor: '#09ab52',
                  cancelButtonColor: '#888888',
                  confirmButtonText: 'Oui, activer',
                  buttons: {
                    cancel: true,
                    confirm: true
                  }
                }, function () {
                      // Call active ws
                      $.ajax({
                        url: calledUrlAvtive,
                        type: "POST",
                        data: {type:"getform"},
                        error: function (request, status, error) {},
                        complete: function () {},
                        statusCode: {
                          //traitement en cas de succès
                          200: function (response) {
                            if(200 == response)
                            {
                              $this.hide();
                              $this = $this.next('.declineDealer');
                              $this.hide();
                              $this.next('.activated').show();
                              //Call Notification WS
                              var msisnd = $('#msisdn').text();
                              var email = $('#dealerEmail').text();
                              notification("regional", msisnd, email, "retailer_accepted", [retailerData]);
                              showButtonsIfApprouved();
                            }
                            return true;
                          }
                        }
                      });
                      swal('Activé!', 'Le retailer est activé.', 'success');
                });
              }
            }
          });
        }
        /*
          If retailer is not duplicated, call active WS,
         */
        else {
          swal({
            title: 'Verification',
            text: stringDuplicated + "Voulez-vous vraiment activer ce retailer pour ce revendeur ?",
            type: 'success',
            showCancelButton: true,
            confirmButtonColor: '#09ab52',
            cancelButtonColor: '#888888',
            confirmButtonText: 'Oui, activer',
            buttons: {
              cancel: true,
              confirm: true
            }
          }, function () {
            //Call active ws
            $.ajax({
              url: calledUrlAvtive,
              type: "POST",
              data: {type:"getform"},
              error: function (request, status, error) {
              },
              complete: function () {
              },
              statusCode: {
                //traitement en cas de succès
                200: function (response) {
                  if(200 == response)
                  {
                    $this.hide();
                    $this = $this.next('.declineDealer');
                    $this.hide();
                    $this.next('.activated').show();
                    $this.next('.activated').removeClass('activated');
                    var msisnd = $('#msisdn').text();
                    var email = $('#dealerEmail').text();
                    notification("regional", msisnd, email, "retailer_accepted", [retailerData]);
                    showButtonsIfApprouved();
                  }
                  return true;
                }
              }
            });
            swal('Activé!', 'Le retailer est activé.', 'success');
          });
        }

        /*
          Afficher le bouton d'activation du revendeur si le nombre de retailers validés >= limit
        */
      });

      /*
          listener Bouton Decliner retailer
       */
      $('.declineDealer').click(function (event) {
        calledUrlDecline = $(this).data("url");
        $this = $(this);
        var retailerData = $(this).data("retailer");
        event.preventDefault();
        swal({
          title: 'Verification',
          text: "Voulez-vous vraiment decliner ce retailer ?",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#da101a',
          cancelButtonColor: '#888888',
          confirmButtonText: 'Oui, decliner',
          buttons: {
            cancel: true,
            confirm: true
          }
        }, function () {
          $.ajax({
            url: calledUrlDecline,
            type: "POST",
            data: {type:"getform"},
            error: function (request, status, error) {},
            complete: function () {},
            statusCode: {
              //traitement en cas de succès
              200: function (response) {
                if(200 == response) {
                  $this.hide();
                  $this = $this.prev('.avtiveDealer');
                  $this.hide();
                  $this.nextAll('.declined').show();
                  $this.nextAll('.declined').removeClass('declined');
                  setTimeout(
                      function () {
                        table.row($this.parents('tr')).remove().draw(false);
                      }, 1500);
                  var msisnd = $('#msisdn').text();
                  var email = $('#dealerEmail').text();
                  notification("regional", msisnd, email, "retailer_not_accepted", [retailerData]);
                  return true;
                }
              }
            }
          });
          swal('Decliné!', 'Le retailer est decliné.', 'error');
        });
      });

      /*
          listener Bouton accepter les Données personnelles
       */
      $('.accepterRevendeur').click(function (event) {
        event.preventDefault();
        swal({
          title: 'Verification',
          text: "Voulez-vous vraiment activer ce revendeur ?",
          type: 'success',
          showCancelButton: true,
          confirmButtonColor: '#09ab52',
          cancelButtonColor: '#888888',
          confirmButtonText: 'Oui, activer',
          buttons: {
            cancel: true,
            confirm: true
          }
        }, function () {
          calledUrl = $('.accepterRevendeur').data("urlaccept");
          $this = $(this);
          $.ajax({
            url: calledUrl,
            type: "POST",
            data: {type:"getform"},
            error: function (request, status, error) {},
            complete: function () {},
            statusCode: {
              //traitement en cas de succès
              200: function (response) {
                if(200 == response){
                  $('.accepterRevendeurNotClicked').hide();
                  $('.accepterRevendeurClicked.validated').show();

                  // Activer les boutons des actions pour les retailers
                  $('.avtiveDealer').prop('disabled', false);
                  $('.declineDealer').prop('disabled', false);

                  //Call WS Notification
                  var msisnd = $('#msisdn').text();
                  var email = $('#dealerEmail').text();
                  notification("regional", msisnd, email, "dealer_unlocked", []);
                }
                return true;
              }
            }
          });
          swal('Activé!', 'Le revendeur est activé.', 'success');
        });
      });

      /*
        listener Bouton accepter les Données personnelles
      */
      $('.declinerRevendeur').click(function (event) {
        event.preventDefault();
        swal({
          title: 'Verification',
          text: "Voulez-vous vraiment refuser ce revendeur ?",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#da101a',
          cancelButtonColor: '#888888',
          confirmButtonText: 'Oui, refuser',
          buttons: {
            cancel: true,
            confirm: true
          }
        }, function () {

          calledUrl = $('.declinerRevendeur').data("urldecline");
          $this = $(this);
          $.ajax({
            url: calledUrl,
            type: "POST",
            data: {type:"getform"},
            error: function (request, status, error) {

            },
            complete: function () {
            },
            statusCode: {

              //traitement en cas de succès
              200: function (response) {
                if(200 == response) {
                  $('.accepterRevendeurNotClicked').hide();
                  $('.accepterRevendeurClicked.declined').show();

                  // Activer les boutons des actions pour les retailers
                  $('.avtiveDealer').prop('disabled', false);
                  $('.declineDealer').prop('disabled', false);

                  setTimeout(
                      function () {
                        location.href = "/admin/revendeurs/list-validation";
                      }, 1500);


                  var msisnd = $('#msisdn').text();
                  var email = $('#dealerEmail').text();
                  notification("regional", msisnd, email, "dealer_locked", []);
                }
                return true;
              }
            }
          });
          swal(
              'Refusé!',
              'Le revendeur est refusé.',
              'error'
          )
        });
      });
  });
  /*
    trigger a click to apply jquery
   */
  $(".paginate_button.previous").trigger("click");


  function notification(by, msisdn, email, action, params) {
    var form = new FormData();
    form.append("assigned_by", by);
    form.append("assigned_to", msisdn);
    form.append("action", action);
    form.append("role", "backoffice");
    form.append("email", email);

    params.forEach(function(element) {
      form.append(element, element);
    });

    var settings = {
      "async": true,
      "crossDomain": true,
      "url": urlNotification,
      "method": "POST",
      "processData": false,
      "contentType": false,
      "mimeType": "multipart/form-data",
      "data": form
    };

    $.ajax(settings).done();
  }

/*
Call count retailers WS, and if count >= limit show buttons
 */
  function showButtonsIfApprouved(){
    if($(".accepterRevendeur").is(":disabled"))
    {
        var pathname = window.location.pathname.split("/");
        var userId = pathname[pathname.length-1];

        var countRetailersUrl = drupalSettings.countRetailers;
        calledUrl = countRetailersUrl + '?id_revendeur=' + userId;
        $.ajax({
          url: calledUrl,
          type: "POST",
          data: {type:"getform"},
          error: function (request, status, error) {

          },
          complete: function () {
          },
          statusCode: {

            //traitement en cas de succès
            200: function (response) {
              if(200 == response){
                $('.accepterRevendeur').prop('disabled', false);
              }
              return true;
            }
          }
        });
    }
  }

} );