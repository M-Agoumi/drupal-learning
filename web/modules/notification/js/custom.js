(function ($, drupalSettings) {

  $('.user-toolbar-tab.toolbar-tab').css('visibility', 'hidden');
  if ($('.toolbar-icon.toolbar-icon-admin-toolbar-tools-help').nextAll("ul").children().length <= 1) {
    $('.toolbar-icon.toolbar-icon-admin-toolbar-tools-help').remove();
  }

  configMenu = $('.menu-item.menu-item--expanded > a:contains("Configuration")');
  revendeursMenu = $('.menu-item.menu-item--expanded > a:contains("Revendeurs")');
  versionMenu = $('.menu-item.menu-item--expanded > a:contains("Gestion des versions")');
  configMenu.append(' <i class="fa fa-caret-down" aria-hidden="true"></i>');
  revendeursMenu.append(' <i class="fa fa-caret-down" aria-hidden="true"></i>');
  versionMenu.append(' <i class="fa fa-caret-down" aria-hidden="true"></i>');
  menu = $('#toolbar-item-administration-tray');
  menu.css('max-height','200px');

  $(document).ready(function () {
    $('a.toolbar-icon').on('click', function () {
      $(this).next("button").click();
    });

    $('.toolbar-menu-administration > .toolbar-menu').append(
        '<li class="menu-item menu-item--expanded" style="float: right">' +
        '<a href="#0" class="my-custom-profile toolbar-icon toolbar-icon-menu-link-content:e8046064-e8d2-4238-b4eb-ff0d41824256"' +
        'data-drupal-link-system-path="<front>">'+
        $('#toolbar-item-user').text()+
        '</a><ul class="toolbar-menu">' +
          '<li class="menu-item"><a href="' +
            $('.toolbar-menu > .account-edit').find('a').attr('href') +
            '" class="toolbar-icon toolbar-icon-menu-link-content:ceb786ca-4773-4205-b632-5f1f402e5e04" data-drupal-link-system-path="'+$('.toolbar-menu > .account-edit').find('a').attr('href')+'">' +
              $('.toolbar-menu > .account-edit').find('a').text()+ '</a>' +
          '</li>' +
          '<li class="menu-item">' +
            '<a href="'+$('.toolbar-menu > .logout').find('a').attr('href')+'" class="toolbar-icon toolbar-icon-menu-link-content:6b63ffca-3c1c-45c0-8eed-b018f0effcb7" data-drupal-link-system-path="' + $('.toolbar-menu > .logout').find('a').attr('href')+ '">' +
              $('.toolbar-menu > .logout').find('a').text()+'</a>' +
          '</li></ul></li>');

        myProfileMenu = $('.my-custom-profile');
        myProfileMenu.append(' <i class="fa fa-caret-down" aria-hidden="true"></i>');

        $('.toolbar-menu-administration').prepend('<li><img border="0" alt="Orange" src="/sites/default/files/img/logo-orange.png" height="36" width="36" style="margin: 2px 12px;"></li>');
    /*
      Param à envoyer et ur pour le nombre des notifications
     */
    var countNotificationUrl = drupalSettings.notificationCountUrl;
    var settings = {
      "async": true,
      "crossDomain": true,
      "url": countNotificationUrl,
      "method": "POST"
    };

    /*
      Mettre à jour le nombre des notifications chaque 10s
     */
    updateBadge(settings);
    window.setInterval(function () {
      updateBadge(settings);
    }, 10000);

    /*
    Disable click in user-name click
     */
    $('#toolbar-item-user').on('click', function () {
      return false;
    });

    /*
    Supprimer Les icones inutiles
     */
    $('#toolbar-item-administration').trigger("click");
    $('.toolbar-icon.toolbar-icon-escape-admin.toolbar-item').remove();
    $('.toolbar-icon.toolbar-icon-menu.trigger.toolbar-item').remove();
    $('button.toolbar-icon.toolbar-icon-toggle-vertical').remove();
    $("a[title='Compte utilisateur']").remove();

    //$('a.toolbar-icon.toolbar-icon-admin-toolbar-tools-help').remove();

    /*
    Afficher les données du profile
     */
    $('.toolbar-icon.toolbar-icon-user.trigger.toolbar-item').addClass("is-active");
    $('div#toolbar-item-administration-tray').addClass("is-active");
    $('.toolbar-tray.toolbar-tray-horizontal-item').addClass("is-active");
    $('#toolbar-item-user-tray').addClass("is-active");
    $('#toolbar-item-user-tray').css('position', 'inherit');
    $('#toolbar-item-administration-tray').css('width', '100%');
    $('nav#toolbar-bar').css('background', '#333333');
    $('.user-toolbar-tab.toolbar-tab').css('border', '1px solid gray');
    $('a#toolbar-item-shortcuts').css('top', '36px');
    $('a#toolbar-item-shortcuts').css('z-index', '1');
    $('a#toolbar-item-devel').css('top', '36px');
    $('a#toolbar-item-devel').css('z-index', '1');

  });

  $('body').show();

  /*
  Fonction pour mettre à jour le badge de notifications
   */
  function updateBadge(settings) {
    $.ajax(settings).done(function (response) {
      if ( $( "#notification-badge" ).length ) {
        if (response['count'] > 0) {
          $("#notification-badge").text(response['count']);
        }
        else{
          $("#notification-badge").remove();
        }
      }
      else{
        if (response['count'] > 0) {
          notificationMenu = $('a:contains("Notifications")');
          notificationMenu.append(" <span id=\"notification-badge\" class=\"badge badge-light\">" + response['count'] + "</span>");
        }
      }
    });
  }



})(jQuery, drupalSettings);