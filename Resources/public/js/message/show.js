(function () {
    'use strict';

    function getPage(tab)
    {
        var page = 1;

        for (var i = 0; i < tab.length; i++) {
            if (tab[i] === 'page') {
                if (typeof(tab[i + 1]) !== 'undefined') {
                    page = tab[i + 1];
                }
                break;
            }
        }

        return page;
    }

    function getSearch(tab)
    {
        var search = '';

        for (var i = 0; i < tab.length; i++) {
            if (tab[i] === 'search') {
                if (typeof(tab[i + 1]) !== 'undefined') {
                    search = tab[i + 1];
                }
                break;
            }
        }

        return search;
    }

    function displayUsers()
    {
        $.ajax({
            url: Routing.generate(
                'claro_message_contactable_users'
            ),
            type: 'GET',
            success: function (datas) {
                $('#groups-nav-tab').attr('class', '');
                $('#users-nav-tab').attr('class', 'active');
                $('#contacts-list').empty();
                $('#contacts-list').append(datas);
            }
        });
    }

    $('#message-users-btn').click(function () {
        displayUsers();
        $('#contacts-box').modal('show');
    });

    $('#users-nav-tab').on('click', function () {
        $('#groups-nav-tab').attr('class', '');
        $(this).attr('class', 'active');
    });

    $('#groups-nav-tab').on('click', function () {
        $('#users-nav-tab').attr('class', '');
        $(this).attr('class', 'active');
    });

    $('body').on('click', '.pagination > li > a', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var element = event.currentTarget;
        var url = $(element).attr('href');
        var route;

        if (url !== '#') {
            var urlTab = url.split('/');
            var page = getPage(urlTab);
            var search = getSearch(urlTab);

            if (search !== '') {
                route = Routing.generate(
                    'claro_message_contactable_users_search',
                    {'page': page, 'search': search}
                );
            } else {
                route = Routing.generate(
                    'claro_message_contactable_users',
                    {'page': page}
                );
            }

            $.ajax({
                url: route,
                success: function (datas) {
                    $('#contacts-list').empty();
                    $('#contacts-list').append(datas);
                },
                type: 'GET'
            });
        }
    });
})();