/**
 * Social Authentication JavaScript
 *
 * @package Agent_Builder
 * @since 0.2.0
 */

(function($) {
    'use strict';

    const Auth = {
        popup: null,
        popupCheckInterval: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Social login buttons
            $(document).on('click', '.social-btn', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const provider = $btn.data('provider');
                const redirectTo = $btn.data('redirect') || window.location.href;
                
                self.initiateOAuth(provider, redirectTo, $btn);
            });
        },

        /**
         * Initiate OAuth flow
         */
        initiateOAuth: function(provider, redirectTo, $btn) {
            const self = this;
            
            // Set loading state
            $btn.addClass('loading');
            const originalText = $btn.find('span').text();
            $btn.find('span').text(agenticAuth.strings.connecting);
            
            // Request auth URL via AJAX
            $.ajax({
                url: agenticAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'agentic_social_login',
                    nonce: agenticAuth.nonce,
                    provider: provider,
                    redirect_to: redirectTo
                },
                success: function(response) {
                    if (response.success && response.data.auth_url) {
                        self.openPopup(response.data.auth_url, provider);
                    } else {
                        self.showError(response.data || agenticAuth.strings.error);
                        $btn.removeClass('loading');
                        $btn.find('span').text(originalText);
                    }
                },
                error: function() {
                    self.showError(agenticAuth.strings.error);
                    $btn.removeClass('loading');
                    $btn.find('span').text(originalText);
                }
            });
        },

        /**
         * Open OAuth popup
         */
        openPopup: function(url, provider) {
            const self = this;
            const width = 600;
            const height = 700;
            const left = (window.innerWidth - width) / 2 + window.screenX;
            const top = (window.innerHeight - height) / 2 + window.screenY;
            
            this.popup = window.open(
                url,
                'agentic_auth_' + provider,
                `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,location=yes,status=no`
            );
            
            if (!this.popup || this.popup.closed) {
                this.showError(agenticAuth.strings.popup_blocked);
                $('.social-btn').removeClass('loading');
                return;
            }
            
            // Focus the popup
            this.popup.focus();
            
            // Check if popup is closed
            this.popupCheckInterval = setInterval(function() {
                if (self.popup && self.popup.closed) {
                    clearInterval(self.popupCheckInterval);
                    $('.social-btn').removeClass('loading');
                    
                    // Restore button text
                    $('.social-btn').each(function() {
                        const provider = $(this).data('provider');
                        const isLogin = window.location.pathname.includes('login');
                        const action = isLogin ? 'Continue with' : 'Sign up with';
                        let name = provider.charAt(0).toUpperCase() + provider.slice(1);
                        if (provider === 'wordpress') name = 'WordPress.com';
                        if (provider === 'twitter') name = 'X';
                        $(this).find('span').text(action + ' ' + name);
                    });
                }
            }, 500);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // Remove existing messages
            $('.auth-message').remove();
            
            // Add error message
            const $error = $('<div class="auth-message error">' + this.escapeHtml(message) + '</div>');
            $('.auth-header').after($error);
            
            // Remove after 5 seconds
            setTimeout(function() {
                $error.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        Auth.init();
    });

    // Listen for messages from popup (alternative to closing popup)
    window.addEventListener('message', function(event) {
        // Verify origin
        if (event.origin !== window.location.origin) {
            return;
        }
        
        if (event.data && event.data.type === 'agentic_auth_success') {
            // Redirect to the specified URL
            if (event.data.redirect) {
                window.location.href = event.data.redirect;
            } else {
                window.location.reload();
            }
        }
    });

})(jQuery);
