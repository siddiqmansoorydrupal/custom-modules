var Collection, collection;

(function($, Drupal, window, document, drupalSettings) {
    "use strict"; // STRICT ES5

    /*
     * Custom class with private & public values ES5
     */
    Collection = function(options) {

        // Private functions

        /**
         * Main lib configuration (private)
         * can be overriden by public method "init(options)"
         */
        var config = {
            classes               :   { // html classes of components
                main              :   'gallery',
                li                :   'card',
                li_active         :   'card--expanded',
                toggle            :   'card__toggle',
                container         :   'slideout',
                btn_close         :   'slideout__close',
                btn_next          :   'slideout__next',
                btn_prev          :   'slideout__prev',
                btn_play          :   'button--icon-controller-play'
            },
            api                   :   undefined,
            logLimit              :   100,  // Max number of log events stored by lib
            jPlayer               :   undefined, // Current audio player instance
            eventQueue            :   {}, // Event Queue instance
            eventHome             :   $(document), // Events will be triggered from here
            eventPrepend          :   'collection-', // All events will be prepended with this
            event: { // Each object is an instance of event which will be stored on log
                configUpdate: function() {
                    return {
                        event: config.eventPrepend + 'config-update',
                        date: new Date()
                    };
                },
                openStart: function(uuid) {
                    return {
                        event: config.eventPrepend + 'open-start',
                        uuid: uuid,
                        date: new Date()
                    };
                },
                openError: function(uuid, desc) {
                    return {
                        event: config.eventPrepend + 'open-error',
                        uuid: uuid,
                        desc: desc,
                        date: new Date()
                    };
                },
                openDone: function(uuid) {
                    return {
                        event: config.eventPrepend + 'open-done',
                        uuid: uuid,
                        date: new Date()
                    };
                },
                show: function(uuid) {
                    return {
                        event: config.eventPrepend + 'show',
                        uuid: typeof uuid != 'undefined' ? uuid : undefined,
                        date: new Date()
                    };
                },
                hide: function(uuid) {
                    return {
                        event: config.eventPrepend + 'hide',
                        uuid: typeof uuid != 'undefined' ? uuid : undefined,
                        date: new Date()
                    };
                },
                loadStart: function(uuid) {
                    return {
                        event: config.eventPrepend + 'load-start',
                        uuid: uuid,
                        date: new Date()
                    };
                },
                loadDone: function(uuid) {
                    return {
                        event: config.eventPrepend + 'load-done',
                        uuid: uuid,
                        date: new Date()
                    };
                },
                found: function(uuid, local) {
                    if (typeof local == 'undefined') {
                        local = true;
                    }
                    return {
                        event: config.eventPrepend + 'found',
                        uuid: uuid,
                        result: local ? 'local' : 'remote',
                        date: new Date()
                    };
                },
                noDataFound: function() {
                    return {
                        event: config.eventPrepend + 'error',
                        desc: 'attribute `data` not found on target element',
                        date: new Date()
                    };
                },
                urlResolved: function(params) {
                    return {
                        event: config.eventPrepend + 'url-resolved',
                        uuid: params.uuid,
                        lang: params.lang,
                        url: params.url,
                        date: new Date()
                    };
                },
                jPlayerReady: function() {
                    return {
                        event: config.eventPrepend + 'jplayer-ready',
                        date: new Date()
                    };
                },
                jPlayerPlay: function($jplayer) {
                  // We manually initialise analytics play events for museums.
                  Drupal.iziGaEvents.analytics.trackPlayFull($jplayer);
                    return {
                        event: config.eventPrepend + 'jplayer-play',
                        date: new Date()
                    };
                },
                jPlayerPause: function() {
                    return {
                        event: config.eventPrepend + 'jplayer-pause',
                        date: new Date()
                    };
                }
            }
        }

        // Execution log Array
        var execution_log = [];

        // Run this to notify the event & save it to log
        function execution(log) {

            config.eventHome.trigger(log.event); // Trigger the event
            queue_run(log); // Run queue check

            // Store/Output log
            if (typeof config.log != 'undefined' && config.log) {

                if (execution_log.length >= config.logLimit) {
                    execution_log.shift()
                }
                execution_log.push(log);

                if (typeof config.log_stream != 'undefined' && config.log_stream) {
                    console.log(log);
                }

            }
        }

        /**
         * Queue functions so they will execute
         * when corresponding event is triggered
         *
         * params:
         * event: String
         * el: Object
         *
         * el.run: Function
         * el.executions: Number (-1 == infinite)
         */

        function queue(event, el) {
            if (typeof event == 'string' && typeof el == 'object') {

                if (typeof el.executions != 'number') {
                    el.executions = 1;
                }

                if (typeof el.run != 'function') {
                    el.run = function() {
                        // Empty function
                    };
                }

                if (!Array.isArray(config.eventQueue[event])) {
                    config.eventQueue[event] = [];
                }

                config.eventQueue[event].push(el);
            }
        }

        function queue_run(log) {
            var list = config.eventQueue[log.event];

            if (Array.isArray(list) && list.length > 0) {

                if (typeof config.log != 'undefined' && config.log) {
                  console.log('Running queued tasks for: ' + log.event);
                }

                for (var i = 0; i < list.length; i++) {
                    if (typeof list[i] == 'object') {
                        if (typeof list[i].run == 'function') {
                            list[i].run();
                        }

                        if (typeof list[i].executions == 'number') {
                            if (list[i].executions > 0) {
                                list[i].executions = list[i].executions - 1;
                            }
                            if (list[i].executions == 0) {
                                delete config.eventQueue[log.event][i];
                            }
                        }
                    }
                }
            }
        }

        /**
         * Override default config & bind main events
         *
         * params:
         *
         * options: Object
         */
        function init(options) {
            if (typeof options != 'undefined') {
                $.extend(config, options);
                execution(config.event.configUpdate());
            }
            bindings();
        }

        /**
         * Open certain target with uuid
         *
         * params:
         *
         * uuid: String
         * override: Boolean
         */
        function open(uuid, override) {
            // trigger event & log update
            execution(config.event.openStart(uuid));
            try {
                hide();
                find(uuid).then(function(target) {
                    config.prevTarget = target;
                    execution(config.event.show(uuid));
                    if (Drupal.iziGaEvents) {
                      const exhibit = drupalSettings.iziMtgInfoChildren[uuid];
                      Drupal.iziGaEvents.analytics.trackOpen(exhibit.language, exhibit.title, exhibit.type, uuid)
                    }
                });

            } catch (e) {
                // trigger error event & log update
                execution(config.event.openError(uuid, e.message));
            } finally {
                // trigger event & log update
                execution(config.event.openDone(uuid));
            }

        }

        function next() {
            $('.' + config.classes.main)
                .find('.' + config.classes.li_active)
                .next().children('a').click();
        }

        function prev() {
            $('.' + config.classes.main)
                .find('.' + config.classes.li_active)
                .prev().children('a').click();
        }

        function show(target) {
            var card = target.parent('.' + config.classes.li);
            card.addClass(config.classes.li_active);
            window.location.href = card.find('.' + config.classes.toggle).attr('href');
            $(window).scrollTo(card.offset().top - 35, 0);
        }

        function hide(target) {
            if (typeof target == 'object') {
                target.parent('.' + config.classes.li)
                    .siblings()
                    .removeClass(config.classes.li_active);

                // trigger event & log update
                execution(config.event.hide(get_uuid(target)));
            } else {
                $('.' + config.classes.main)
                    .find('.' + config.classes.li)
                    .removeClass(config.classes.li_active);

                // trigger event & log update
                execution(config.event.hide());
            }
            jPlayerPause();
        }

        function find(uuid, override) {

            override = typeof override != 'undefined' ? override : false;
            var target = $('.' + config.classes.container)
                .filter('div[data-uuid="' + uuid + '"]');
            return $.Deferred(function(promise) {
                if (typeof target == 'object') {
                    let target_html = target.html().trim();
                    if (target_html == '' || override) {

                        var uuid = get_uuid(target);
                        toggleLoadAnimation(target);
                        show(target);
                        // trigger event & log update
                        execution(config.event.loadStart(uuid));

                        target.load(url(target), function() {
                            initContent(target);
                            execution(config.event.loadDone(uuid));
                            promise.resolve(target);
                        });

                    } else {
                        show(target);
                        promise.resolve(target);
                    }
                } else {
                    promise.resolve(undefined);
                }

            });
        }

        function toggleLoadAnimation(target) {

            target.html('<div class="loader"></div>');
        }

        function url(target) {

            var params = {
                uuid: get_uuid(target),
                lang: get_lang(target)
            };

            var url = config.api + '';
            for (var key in params) {
                url = url.replace(':' + key, params[key]);
            }

            // trigger event & log update
            params.url = url;
            execution(config.event.urlResolved(params));
            return url;
        }

        function get_uuid(target) {
            if (typeof target == 'object') {
                return target.data('uuid');
            } else {
                // trigger error event & log update
                execution(config.event.noDataFound());
                return undefined;
            }
        }

        function get_lang(target) {
            if (typeof target == 'object') {
                return target.data('lang');
            } else {
                // trigger error event & log update
                execution(config.event.noDataFound());
                return undefined;
            }
        }

        function initContent(content) {

            // Close button event
            $('.' + config.classes.btn_close, content).on('click', function(e) {
                e.preventDefault();
                hide();
            });

            // Next button event
            $('.' + config.classes.btn_next, content).on('click', function(e) {
                e.preventDefault();
                next();
            });

            // Prev button event
            $('.' + config.classes.btn_prev, content).on('click', function(e) {
                e.preventDefault();
                prev();
            });

            $('.js-fancybox', content).fancybox({
              maxWidth: 600,
              maxHeight: '60%',
              padding: 0,
              margin: 24,
              closeBtn: true,
              arrows: false,
              scrollOutside: false,
              beforeShow: function() {

                $('html').addClass('fancybox-open');

              },
              afterClose: function() {

                $('html').removeClass('fancybox-open');

              }
            });

            $('.js-fancybox-video').fancybox();

            slideShowInit($('.js-slideshow', content), {
                backgroundSize: 'contain'
            });

            jplayerInit(content);
            Drupal.attachBehaviors(content[0], Drupal.settings);
        }

        function jplayerInit(context) {

            var $jplayer = $('.jp-jplayer', context).first();
            let media_url = $jplayer.data('url');
            let cssSelectorAncestor = "#jp_container_"+$jplayer.data('uuid');

            // Point to the slide controllers.
            var myControl = {
                progress: $jplayer.next().find('.jp-progress-slider')
            };

            // Initialize jPlayer
            var myPlayer = $jplayer.jPlayer({
                ready: function() {
                    $jplayer.jPlayer("setMedia", {
                        m4a: media_url
                    });
                    config.jPlayer = $jplayer;
                    execution(config.event.jPlayerReady());
                    audioTourPlayingAutomatically(1);
                },
                timeupdate: function(event) {
                    myControl.progress.slider("value", event.jPlayer.status.currentPercentAbsolute);
                },
                play: function() { // To avoid both jPlayers playing together.
                    $jplayer.jPlayer("pauseOthers");
                    execution(config.event.jPlayerPlay($jplayer));
                },
                pause: function() {
                    execution(config.event.jPlayerPause());
                },
                ended: function() {
                    next();
                },
                cssSelectorAncestor: cssSelectorAncestor,
                swfPath: drupalSettings.jplayerSwfPath,
                supplied: "m4a",
                useStateClassSkin: true,
                autoBlur: false,
                smoothPlayBar: true,
                keyEnabled: true,
                remainingDuration: true,
                toggleDuration: true,
                preload: 'metadata'
            });

            var myPlayerData = myPlayer.data("jPlayer");

            // Create the progress slider control functionality.
            myControl.progress.slider({
                animate: "fast",
                max: 100,
                range: "min",
                step: 0.1,
                value: 0,
                slide: function(event, ui) {
                    var sp = myPlayerData.status.seekPercent;
                    if (sp > 0) {
                        // Move the play-head to the value and factor in the seek percent.
                        myPlayer.jPlayer("playHead", ui.value * (100 / sp));
                    } else {
                        // Create a timeout to reset this slider to zero.
                        setTimeout(function() {
                            myControl.progress.slider("value", 0);
                        }, 0);
                    }
                }
            });
        }

        function jPlayerPlay() {
            if (typeof config.prevTarget != 'undefined') {
                config.prevTarget.closest('.' + config.classes.container).siblings('a').click();
                config.prevTarget.find('.jp-jplayer').first().jPlayer("play");
            } else {
                queue(config.event.jPlayerReady().event, {
                    run: function() {
                        config.jPlayer.jPlayer('play');
                        execution(config.event.jPlayerPlay());
                    },
                    executions: 1
                });

                var target = $('.' + config.classes.main).children().first()
                    .find('.' + config.classes.container);

                target.siblings('a').click();
            }
        }

        function jPlayerPause() {
            if (typeof config.prevTarget != 'undefined') {
                config.prevTarget.find('.jp-jplayer').first().jPlayer("pause");
                execution(config.event.jPlayerPause());
            }
        }

        function bindings() {

            // Open trigger
            $('.card__toggle').on('click', function(e) {
                e.preventDefault();
                // if is manually event
                if (e.originalEvent !== undefined){
                    audioTourPlayingAutomatically(0)
                }
                var uuid = $(this).siblings('.' + config.classes.container).first().data('uuid');
                open(uuid);
            });

            $('.' + config.classes.btn_play).on('click', function(e) {
                e.preventDefault();

                jPlayerPlay();

                // Add class to play aduio tour button
                $(this).addClass('playing-audio-tour');
            });

            config.eventHome.on(config.event.openStart().event, function(e) {
                e.preventDefault();

                jPlayerPause();
            });
            config.eventHome.on(config.event.hide().event, function(e) {
                e.preventDefault();

                jPlayerPause();
            });

        }

        function audioTourPlayingAutomatically(action){
            if($('a.button--icon-controller-play').hasClass('playing-audio-tour')){
                if(action == 1) {
                    jPlayerPlay();
                }else{
                    $('a.button--icon-controller-play').removeClass('playing-audio-tour')
                }
            }
        }

        // Public functions
        this.public = {

            init: function(options) {
                init(options);
            },

            reset: function() {
                $('.' + config.classes.main).find('.' + config.classes.target).html('');
                execution_log = [];
                hide();
            },

            log: function() {
                return execution_log;
            },

            log_flush: function() {
                execution_log = [];
                return execution_log;
            },

            log_toogle: function(state) {
                if (typeof config.log == 'undefined') {
                    config.log = true;
                } else {
                    config.log = !config.log;
                }
                return config.log;
            },

            log_stream: function() {
                if (typeof config.log_stream == 'undefined') {
                    config.log_stream = true;
                } else {
                    config.log_stream = !config.log_stream;
                }
                return config.log_stream;
            },

            play: function() {
                jPlayerPlay();
            },
            pause: function() {
                jPlayerPause();
            }
        };
    };

    // Update class Prototype, so public vars/functions can be accesible
    Collection.prototype = new Collection().public;

}(this.jQuery, this.Drupal, this, this.document, this.drupalSettings));

collection = new Collection();
collection.init({
    api: '/izi_apicontent/exhibit/:uuid/:lang'
});
