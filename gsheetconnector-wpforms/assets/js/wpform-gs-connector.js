jQuery(document).ready(function ($) {

  /**
   * Handle saving the Google Sheets auth code for WPForm integration.
   * Sends the entered code to the server and displays the result.
   */
  $(document).on("click", "#save-wpform-gs-code", function (event) {

    event.preventDefault();

    jQuery("#save-wpform-gs-code")
      .prop("disabled", true)
      .addClass("common-disable");

    $('html, body').animate({
      scrollTop: $("input[name='google-access-code']").offset().top - 100
    }, 600);

    // Show loading indicator while request is in progress.
    $(".loading-sign").addClass("loading");

    var data = {
      action: "gscwpf_verify_integration",
      code: $("input[name='google-access-code']").val(),
      security: $("#gs-ajax-nonce").val()
    };

    $.post(gsc_ajax.ajax_url, data)

    .done(function (response) {

      // Remove loading indicator and clear previous message.
      $(".loading-sign").removeClass("loading");
      $("#gscwpff-validation-message").empty();

      if (!response.success) {

        // Display error message returned from the server.
        $("<div class='gsc-msg gsc-error fw-400 text-dark text-center pt-10 pb-10 manual-margin'>"
          + response.data.message +
        "</div>").appendTo("#gscwpff-validation-message");

      } else {

        // Display success message and remove the code query param from the URL.
        $("<div class='gsc-msg gsc-success fw-400 text-dark text-center pt-10 pb-10 manual-margin'>"
          + response.data.message +
        "</div>").appendTo("#gscwpff-validation-message");

          setTimeout(function () {
          window.location.href = jQuery("#redirect_auth_wpforms").val();
        }, 1000);


      }

    })

    .fail(function (xhr) {

      // Hide loading indicator and show generic AJAX error.
      $(".loading-sign").removeClass("loading");

      console.log("AJAX ERROR:", xhr.responseText);

      $("#gscwpff-validation-message").html(
        "<div class='gsc-msg gsc-error fw-400 text-dark text-center pt-10 pb-10 manual-margin'>AJAX request failed.</div>"
      );

      setTimeout(function () {
        var url = new URL(window.location.href);
        url.searchParams.delete("code");
        window.location.href = url.toString();
      }, 1000);

    });

  });


  /**
   * Save Auth Method
   */
  jQuery(document).on("click", "#gscwpf-save-auth-method", function (e) {
      e.preventDefault();

      var $btn     = jQuery("#gscwpf-save-auth-method");
      var $loading = jQuery(".loading-sign-auth-method");

      $btn.prop("disabled", true);
      $loading.addClass("loading");

      var data = {
          action:          "gscwpf_save_auth_method",
          api_auth_method: jQuery("#gs_wpforms_dro_option").val(),
          security:        jQuery("#gs-ajax-nonce").val(),
      };

      jQuery.post(ajaxurl, data, function (response) {

          /* Remove loading indicator and clear previous message */
          $loading.removeClass("loading");
          $btn.prop("disabled", false);
          jQuery("#gscwpf-auth-method-message").empty();

          if ( ! response.success ) {

              /* Display error message returned from the server */
              jQuery("<div class='gsc-msg gsc-error fw-400 text-dark text-center pt-10 pb-10 manual-margin'>"
                  + response.data.message +
              "</div>").appendTo("#gscwpf-auth-method-message");

          } else {
              /* reload the page */
              location.reload();

          }

      }).fail(function (jqXHR, textStatus, errorThrown) {

          /* Remove loading indicator and clear previous message */
          $loading.removeClass("loading");
          $btn.prop("disabled", false);
          jQuery("#gscwpf-auth-method-message").empty();

          /* Display network error message */
          jQuery("<div class='gsc-msg gsc-error fw-400 text-dark text-center pt-10 pb-10 manual-margin'>"
              + "AJAX request failed. Please try again." +
          "</div>").appendTo("#gscwpf-auth-method-message");

      });

  });

  /**
   * Decode HTML entities in a string.
   * @param {string} input - HTML-encoded string.
   * @returns {string} Decoded string.
   */
  function html_decode(input) {
    var doc = new DOMParser().parseFromString(input, "text/html");
    return doc.documentElement.textContent;
  }

  /**
   * When a WPForm is selected, request the form data and open the returned URL.
   */
  $("#wpforms_select").change(function (e) {
    e.preventDefault();
    var FormId = $(this).val();
    if (FormId != "") {
      $(".loading-sign-select").addClass("loading");
      $.ajax({
        type: "POST",
        url: ajaxurl,
        dataType: "json",
        data: {
          action: "get_wpforms",
          wpformsId: FormId,
          security: $("#wp-ajax-nonce").val(),
        },
        cache: false,
        success: function (data) {
           $(".loading-sign-select").removeClass("loading");
          if (data["data_result"] == "") {
            return;
          } else {
            window.open(data.data, "_blank");
          }
        },
      });
    }
  });

  /**
   * Clear debug
   */
  $(document).on("click", ".debug-clear-kk", function () {
    $(".clear-loading-sign").addClass("loading");
    var data = {
      action: "wp_clear_logs",
      security: $("#gs-ajax-nonce").val(),
    };
    $.post(ajaxurl, data, function (response) {
      var clear_msg = response.data;
      if (response.success) {
        $(".clear-loading-sign").removeClass("loading");
        $("#gs-validation-message").empty();
        $("<span class='gs-valid-message'>" + clear_msg + "</span>").appendTo("#gs-validation-message");
        setTimeout(function () {
          location.reload();
        }, 1000);
      }
    });
  });

  /**
   * Deactivate the api code
   * @since 1.0
   */

  // Open popup instead of alert
  $(document).on("click", "#gscwpff-confirm-deactive-popup-btn", function (e) {
    e.preventDefault();
    $("#gscwpff-confirm-deactive-popup-free").removeClass("d-none");
  });

  // Cancel button
  $(document).on("click", "#gscwpff-deactive-popup-free-cancel", function () {
    $("#gscwpff-confirm-deactive-popup-free").addClass("d-none");
  });

  // Click outside popup to close
  $(document).on("click", "#gscwpff-confirm-deactive-popup-free", function (e) {
    if ($(e.target).is(this)) {
      $(this).addClass("d-none");
    }
  });

  $(document).on("click", "#wpform-free-deactivate", function () {
    $(".loading-sign-deactive").addClass("loading");
    $("#gscwpff-confirm-deactive-popup-free").addClass("d-none");

    var data = {
      action: "deactivate_wpformgsc_integation",
      security: $("#gs-ajax-nonce").val(),
    };
    $.post(ajaxurl, data, function (response) {
      if (response == -1) {
        return false; /* Invalid nonce */
      }

      if (!response.success) {
        alert("Error while deactivation");
        $(".loading-sign-deactive").removeClass("loading");
        $("#gsc-validation-deactivate-message").empty();
      } else {
        $(".loading-sign-deactive").removeClass("loading");
        $("#gsc-validation-deactivate-message").empty();
        $("<div class='gsc-msg gsc-success fw-400 text-dark text-center pt-10 pb-10 manual-margin'>Your account is removed, now reauthenticate to configure WPForms to Google Sheet.</div>")
          .appendTo("#gsc-validation-deactivate-message");
        setTimeout(function () {
          location.reload();
        }, 1000);
      }
    });
  });

  /**
   * Display Error logs
   */
  $(".wp-system-Error-logs").hide();

  var isOpen = false;

  function toggleLogs() {
    $(".wp-system-Error-logs").toggle();
    $(".wpgsc-logs").text(isOpen ? "View" : "Close");
    isOpen = !isOpen;
  }

  $(".wpgsc-logs").on("click", function () {
    toggleLogs();
  });

  $(".wp-system-Error-logs").on("click", function (e) {
    e.stopPropagation();
  });

  $(".close-button").on("click", function () {
    $(".wp-system-Error-logs").hide();
    $(".wpgsc-logs").text("View");
    isOpen = false;
  });

  // Msg Hide
  if (localStorage.getItem("googleDriveMsgHidden") === "true") {
    $("#google-drive-msg").hide();
  }

  $(".button_wpformgsc").on("click", function () {
    $("#google-drive-msg").hide();
    localStorage.setItem("googleDriveMsgHidden", "true");
  });

  $("#wp-deactivate-log").on("click", function () {
    $("#google-drive-msg").show();
    localStorage.removeItem("googleDriveMsgHidden");
  });

  /**
   * Clear debug for system status tab
   */
  $(document).on("click", ".clear-content-logs-wp", function () {
    $(".clear-loading-sign-logs-wp").addClass("loading");
    var data = {
      action: "wp_clear_debug_logs",
      security: $("#gs-ajax-nonce").val(),
    };
    $.post(ajaxurl, data, function (response) {
      if (response == -1) {
        return false; // Invalid nonce
      }

      if (response.success) {
        $(".clear-loading-sign-logs-wp").removeClass("loading");
        $(".clear-content-logs-msg-wp").html("Logs are cleared.");
        setTimeout(function () {
          location.reload();
        }, 1000);
      }
    });
  });

  /**
   * Addons list blank div handling
   */
  $(".gsheetconnector-addons-list").each(function () {
    if ($(this).html().trim().length === 0) {
      $(this).addClass("blank_div");
      $(this).prev("h2").hide();
    }
  });

  /**
   * Install plugin button
   */
  $(".gscwpfrom-install-plugin-btn-pro").on("click", function () {
    var button = $(this);
    var pluginSlug = button.data("plugin");
    var downloadUrl = button.data("download");
    var loaderSpan = button.closest(".button-bar").find(".loading-sign-install");

    loaderSpan.addClass("loading");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "gscwpform_install_plugin",
        plugin_slug: pluginSlug,
        download_url: downloadUrl,
        security: $("#gscwpform_ajax_nonce").val(),
      },
      success: function (response) {
        loaderSpan.removeClass("loading");
        if (response.success) {
          button.hide();
          button.closest(".button-bar").find(".gscwpform-activate-plugin-btn-pro").show();
        } else {
          button.html("Install Now").prop("disabled", false);
          alert(response.data.message);
        }
      },
      error: function () {
        loaderSpan.removeClass("loading");
        button.html("Install Now").prop("disabled", false);
        alert(response.data.message);
      },
    });
  });

  /**
   * Handle plugin activation button click via AJAX.
   *
   * - Shows loading spinner
   * - Sends plugin slug to server for activation
   * - On success, updates button to "Activated" and reloads page
   * - On error or failure, resets button and removes loading state
   */
  $(document).on("click", ".gscwpform-activate-plugin-btn-pro", function () {
    var button = $(this);
    var pluginSlug = button.data("plugin");
    var loaderSpan = button.siblings(".loading-sign-active");
    loaderSpan.addClass("loading");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "gscwp_activate_plugin",
        plugin_slug: pluginSlug,
        security: $("#gscwpform_ajax_nonce").val(),
      },
      success: function (response) {
        if (response.success) {
          button.text("Activated");
          button.prop("disabled", true);
          location.reload();
        } else {
          loaderSpan.removeClass("loading");
          button.prop("disabled", false);
        }
      },
      error: function () {
        loaderSpan.removeClass("loading").text("");
        button.prop("disabled", false);
      },
    });
  });

  /**
   * Handle plugin deactivation button click via AJAX.
   *
   * - Sends plugin slug to server for deactivation
   * - On success, shows alert and reloads the page
   * - On error, shows AJAX error alert
   */
  $(".gscwpform-deactivate-plugin-pro").on("click", function () {
    var pluginSlug = $(this).data("plugin");
    $.ajax({
      url: ajaxurl,
      type: "POST",
      dataType: "json",
      data: {
        action: "gscwpform_deactivate_plugin",
        plugin_slug: pluginSlug,
        security: $("#gscwpform_ajax_nonce").val(),
      },
      success: function (response) {
        if (response.success) {
          alert(response.data);
          location.reload();
        }
      },
      error: function (xhr, status, error) {
        alert("AJAX error: " + error);
      },
    });
  });

  /**
   * Popup — open based on selected option
   */
  var selectBox   = $("#gs_wpforms_dro_option");
  var manualPopup = $("#gscwpff-confirm-manual-popup-pro");

  selectBox.on("change", function () {
    var value = $(this).val();

    manualPopup.addClass("d-none");

    if (value === "1") {
      manualPopup.removeClass("d-none");
    } 
  });

  $(document).on("click", ".gscwpff-popup-close-pro", function () {
    manualPopup.addClass("d-none");
    selectBox.val("0").trigger("change");
  });

  $(document).on("click", ".gscwpff-popup-overlay", function (e) {
    if ($(e.target).hasClass("gscwpff-popup-overlay")) {
      manualPopup.addClass("d-none");
      selectBox.val("0").trigger("change");
    }
  });

  /**
   * Save uninstall settings
   */
  var $checkbox = $("#gscwpff_wpforms_uninstall_settings_free");
  var $saveBtn  = $(".gscwpff-uninstall-settings-save-free");
  var $msg      = $("#gsc_wpforms-uninstall-msg-free");
  var $loader   = $(".loading-uninstall-free");
  var $popup    = $("#gscwpff-confirm-uninstall-data-popup-free");

  $saveBtn.prop("disabled", true).addClass("common-disable");

  $checkbox.on("change", function () {
    if ($(this).is(":checked")) {
      $(this).prop("checked", false);
      $popup.removeClass("d-none");
      return;
    }
    $saveBtn.prop("disabled", false).removeClass("common-disable");
  });

  $("#gscwpff-confirm-enable-uninstall-free").on("click", function () {
    $checkbox.prop("checked", true);
    $popup.addClass("d-none");
    $saveBtn.prop("disabled", false).removeClass("common-disable");
  });

  $("#gscwpff-cancel-uninstall-free").on("click", function () {
    $checkbox.prop("checked", false);
    $popup.addClass("d-none");
  });

  $saveBtn.on("click", function (e) {
    e.preventDefault();

    var isChecked = $checkbox.is(":checked");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      dataType: "json",
      data: {
        action: "gscwpff_save_uninstall_settings_ajax_free",
        uninstall_setting: isChecked ? 1 : 0,
        security: $("#gsc-wpforms-setting-ajax-nonce").val(),
      },
      beforeSend: function () {
        $loader.addClass("loading");
        $saveBtn.prop("disabled", true).addClass("common-disable");
      },
      success: function (response) {
        if (!response.success) return;

        $msg.removeClass("gsc-success gsc-error d-none");
        $msg.addClass("gsc-success").text("Plugin preferences updated successfully");

        setTimeout(function () {
          $msg.addClass("d-none").text("");
        }, 2000);
      },
      error: function () {
        $msg.removeClass("d-none").addClass("gsc-error").text("Something went wrong");
        $saveBtn.prop("disabled", false).removeClass("common-disable");
      },
      complete: function () {
        $loader.removeClass("loading");
      },
    });
  });


  /**
   * Auto-submit Google auth code if present in URL
   */
  /*var params = new URLSearchParams(window.location.search);
  var code   = params.get("code");

  if (code) {
    var input  = $("input[name='google-access-code']").filter(function () {
      return $(this).val().trim() !== "";
    }).first();

    var button = $("#save-wpform-gs-code");

    if (!button.data("auto-triggered")) {

      function tryAutoSubmit() {
        var val = input.val();
        if (val && val.trim() !== "") {
          button.data("auto-triggered", true);
          setTimeout(function () {
            button.trigger("click");
          }, 300);
          return true;
        }
        return false;
      }

      if (!tryAutoSubmit()) {
        setTimeout(tryAutoSubmit, 500);
      }
    }
  }*/
}); // end jQuery(document).ready


/* ─── DOMContentLoaded blocks (kept separate — non-jQuery) ─── */

/* Slider */
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".gsc-slider-wrapper").forEach(function (wrapper) {
    const slider = wrapper.querySelector(".gsc-slider");
    const slides = wrapper.querySelectorAll(".gsc-slide");
    const prevBtn = wrapper.querySelector(".gsc-nav.prev");
    const nextBtn = wrapper.querySelector(".gsc-nav.next");

    let current = 0;

    function updateSlider() {
      slider.style.transform = "translateX(" + -current * 100 + "%)";
    }

    nextBtn.addEventListener("click", function () {
      current = (current + 1) % slides.length;
      updateSlider();
    });

    prevBtn.addEventListener("click", function () {
      current = (current - 1 + slides.length) % slides.length;
      updateSlider();
    });
  });
});

/* Select box */
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("select.gsc-select").forEach((select) => {
    if (select.classList.contains("auto-processed")) return;
    select.classList.add("auto-processed");

    select.style.display = "none";

    const wrapper = document.createElement("div");
    wrapper.className = "auto-select";

    const display = document.createElement("div");
    display.className = "auto-select-display";
    display.innerText = select.options[select.selectedIndex]?.text || "Select";

    const optionsBox = document.createElement("div");
    optionsBox.className = "auto-select-options";

    [...select.options].forEach((opt, index) => {
      const item = document.createElement("div");
      item.className = "auto-select-option";
      item.innerText = opt.text;

      item.addEventListener("click", () => {
        select.selectedIndex = index;
        display.innerText = opt.text;
        optionsBox.style.display = "none";
        select.dispatchEvent(new Event("change", { bubbles: true }));
      });

      optionsBox.appendChild(item);
    });

    display.addEventListener("click", (e) => {
      e.stopPropagation();
      optionsBox.style.display =
        optionsBox.style.display === "block" ? "none" : "block";
    });

    wrapper.appendChild(display);
    wrapper.appendChild(optionsBox);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
  });

  document.addEventListener("click", function (e) {
    document.querySelectorAll(".auto-select-options").forEach((box) => {
      if (!box.parentElement.contains(e.target)) {
        box.style.display = "none";
      }
    });
  });
});

/* Badge */
document.addEventListener("DOMContentLoaded", function () {
  var el = document.querySelector(".gscwpff-selected-method");
  var getvalue = el.getAttribute("data-value");

  if (el && el.dataset.value && el.dataset.value.trim() !== "") {
    var badgeText = el.dataset.value.trim();

    document.querySelectorAll(".nav-tab-wrapper .nav-tab").forEach(function (tab) {
      var href = tab.getAttribute("href") || "";

      if (href.indexOf("tab=integration") !== -1) {
        tab.style.position = "relative";

        if (tab.querySelector(".gscwpff-selected-badge")) return;

        var badge = document.createElement("div");

        if (getvalue == "Auth Required") {
          badge.className = "gscwpff-selected-authrequired-badge";
        } else {
          badge.className = "gscwpff-selected-badge";
        }

        badge.textContent = badgeText;
        tab.appendChild(badge);
      }
    });
  }

  var deactivateBtn = document.querySelector('input[name="gscwpff_license_deactivate"]');

  if (deactivateBtn) {
    deactivateBtn.addEventListener("click", function () {
      document.querySelectorAll(".gscwpff-selected-badge").forEach(function (badge) {
        badge.remove();
      });
    });
  }
});

/* Auto-scroll to permission error notice if present */
document.addEventListener("DOMContentLoaded", function () {
  var permissionError = document.getElementById('gsc-permission-error');
  if (permissionError) {
    permissionError.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});



/**
 * Service Account JSON — Upload, Save, Deactivate
 * Handles the UI interactions for managing the service account JSON credentials,
 * including file upload, manual input, saving via AJAX, and deactivation with confirmation.
 * @since 4.0.4
 */
jQuery(document).ready(function () {

    /* -------------------------------------------------------
     * VARIABLES
     * ------------------------------------------------------- */
    var $textarea   = jQuery("#gs_wpforms_service_json");
    var $saveBtn    = jQuery("#gs_wpforms_save_service_json");
    var $fileInput  = jQuery("#gs_wpforms_upload_json");
    var $loader     = jQuery(".loading-sign-service-auth");
    var $popup      = jQuery("#gs-wpforms-confirm-service-popup");
    var $msgBox     = jQuery("#gscwpf-service-auth-method-message");

    /* -------------------------------------------------------
     * HELPER — Show validation message
     * ------------------------------------------------------- */
    function showMessage( message, type ) {
        $msgBox.empty();
        jQuery("<div class='gsc-msg fw-400 text-dark text-center pt-10 pb-10 manual-margin " + type + "'>"
            + message +
        "</div>").appendTo( $msgBox );
    }

    /* -------------------------------------------------------
     * HELPER — Toggle save button based on textarea content
     * ------------------------------------------------------- */
    function toggleSaveButton() {
        var isEmpty = jQuery.trim( $textarea.val() ) === "";
        $saveBtn
            .prop("disabled", isEmpty)
            .toggleClass("common-disable", isEmpty);
    }

    /* On page load */
    toggleSaveButton();

    /* -------------------------------------------------------
     * TEXTAREA — When user types or pastes manually
     * ------------------------------------------------------- */
    $textarea.on("input", function () {
        $msgBox.empty();
        toggleSaveButton();
    });

    /* -------------------------------------------------------
     * FILE UPLOAD — Handle JSON file selection
     * ------------------------------------------------------- */
    $fileInput.on("change", function (e) {
        var file = e.target.files[0];

        if ( ! file ) {
            return;
        }

        /* Only allow .json files */
        if ( file.type !== "application/json" && ! file.name.endsWith(".json") ) {
            showMessage(
                "Invalid file type. Please upload a .json file.",
                "gsc-error"
            );
            $fileInput.val("");
            return;
        }

        var reader = new FileReader();

        reader.onload = function (event) {
            try {
                var parsed  = JSON.parse( event.target.result );
                var content = JSON.stringify( parsed, null, 2 );

                $textarea.val( content ).trigger("input");

                showMessage(
                    "JSON file loaded successfully.click save to authenticate",
                    "gsc-success"
                );

            } catch (err) {
                showMessage(
                    "Invalid JSON file. Please check the file and try again.",
                    "gsc-error"
                );
                $fileInput.val("");
                $textarea.val("").trigger("input");
            }
        };

        reader.onerror = function () {
            showMessage(
                "File could not be read. Please try again.",
                "gsc-error"
            );
            $fileInput.val("");
        };

        reader.readAsText( file );
    });

    /* -------------------------------------------------------
     * SAVE JSON — Save service account credentials
     * ------------------------------------------------------- */
    jQuery(document).on("click", "#gs_wpforms_save_service_json", function () {

        var json = $textarea.val();

        $msgBox.empty();

        if ( ! json || jQuery.trim( json ) === "" ) {
            showMessage(
                "Please paste or upload your JSON credentials before saving.",
                "gsc-error"
            );
            return;
        }

        $loader.addClass("loading");

        var data = {
            action:   "gscwpf_save_service_account_json",
            json:     json,
            security: jQuery("#gs-ajax-nonce").val(),
        };

        jQuery.post(ajaxurl, data, function (response) {

            $loader.removeClass("loading");

            if ( response.success ) {
                showMessage( response.data.message, "gsc-success" );
                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                showMessage( response.data.message, "gsc-error" );
            }

        }).fail(function () {
            $loader.removeClass("loading");
            showMessage( "AJAX request failed. Please try again.", "gsc-error" );
        });

    });

    /* -------------------------------------------------------
     * DEACTIVATE — Step 1: Open confirm popup
     * ------------------------------------------------------- */
    jQuery(document).on("click", "#gs_wpforms_deactivate_service_auth", function (e) {
        e.preventDefault();
        $msgBox.empty();
        $popup.removeClass("d-none");
    });

    /* -------------------------------------------------------
     * DEACTIVATE — Step 2: Cancel closes popup
     * ------------------------------------------------------- */
    jQuery(document).on("click", "#gs-wpforms-service-popup-cancel", function () {
        $popup.addClass("d-none");
    });

    /* -------------------------------------------------------
     * DEACTIVATE — Step 3: Confirm runs AJAX
     * ------------------------------------------------------- */
    jQuery(document).on("click", "#gs-wpforms-service-popup-confirm", function () {

        $popup.addClass("d-none");
        $loader.addClass("loading");
        $msgBox.empty();

        var data = {
            action:   "gscwpf_deactivate_service_account",
            security: jQuery("#gs-ajax-nonce").val(),
        };

        jQuery.post(ajaxurl, data, function (response) {

            $loader.removeClass("loading");

            if ( response.success ) {
                showMessage( response.data.message, "gsc-success" );
                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                showMessage( response.data.message, "gsc-error" );
            }

        }).fail(function () {
            $loader.removeClass("loading");
            showMessage( "AJAX request failed. Please try again.", "gsc-error" );
        });

    });

    /* -------------------------------------------------------
     * DEACTIVATE — Step 4: Close popup on overlay click
     * ------------------------------------------------------- */
    jQuery(document).on("click", "#gs-wpforms-confirm-service-popup", function (e) {
        if ( jQuery(e.target).is("#gs-wpforms-confirm-service-popup") ) {
            $popup.addClass("d-none");
        }
    });

});






/** jQuery for notification slider  */
document.addEventListener("DOMContentLoaded", function() {
    const slides = document.querySelectorAll(".notification-gscwpff-slide");
    const prevBtn = document.querySelector(".notification-gscwpff-slider-btn.prev");
    const nextBtn = document.querySelector(".notification-gscwpff-slider-btn.next");
    let index = 0;
      function updateSlider() {
          if (!slides || slides.length === 0) return;
          slides.forEach(slide => slide.classList.remove("active"));
          if (slides[index]) {
              slides[index].classList.add("active");
          }
      }

      nextBtn.addEventListener("click", function() {
          index++;
          if (index >= slides.length) {
              index = 0;
          }
          updateSlider();
      });

      prevBtn.addEventListener("click", function() {
          index--;
          if (index < 0) {
              index = slides.length - 1;
          }
          updateSlider();
      });
      updateSlider();
});


/** jquery for hide arrow if no one slider banner   */
jQuery(document).ready(function ($) {
  if ($(".notification-gscwpff-slide").length <= 1) {
    $(".notification-gscwpff-slider-arrows").hide();
  }

  if ($(".notification-gscwpff-slide").length == 0) {
    $(".notification-gscwpff-notice-slider").hide();
  }
});

jQuery(document).ready(function ($) {
  let totalSlides = $(
    ".notification-gscwpff-slider-track .notification-gscwpff-slide",
  ).length;

  if (totalSlides <= 1) {
  }

  function showNextSlide(currentSlide) {
    var track = currentSlide.closest(".notification-gscwpff-slider-track");
    var slides = track.find(".notification-gscwpff-slide");
    var currentIndex = slides.index(currentSlide);
    var nextIndex = currentIndex + 1;

    currentSlide.remove();

    slides = track.find(".notification-gscwpff-slide");

    if (slides.length > 0) {
      if (nextIndex >= slides.length) {
        nextIndex = 0;
      }
      slides.hide().eq(nextIndex).show();
    }
    location.reload();
  }

 /** For JQuery for Dismiss notification */
  jQuery(document).on(
    "click",
    ".gscwpff-review-close, .gscwpff-review-dismiss-btn  , .gscwpff-showpro-close, .gscwpff-addons-close, .gscwpff-enhance-btn-later, .gscwpff-review-btn-later, .gscwpff-enhance-close",
    function () {
      var key = jQuery(this).data("key");
      var currentSlide = jQuery(this).closest(".notification-gscwpff-slide");

      jQuery.post(
        ajaxurl,
        {
          action: "gscwpff_dismiss_notice",
          key: key,
          security: jQuery("#gswpff-banner-ajax-nonce").val(),
        },
        function () {
          showNextSlide(currentSlide);
        },
      );
    },
  );

  /** For JQuery for snooze notification */
  jQuery(document).on(
    "click",
    ".gscwpff-review-btn-later, .gscwpff-Showpro-btn-later, .gscwpff-addons-btn-later",
    function () {
      var key = jQuery(this).data("key");
      var currentSlide = jQuery(this).closest(".notification-gscwpff-slide");

      jQuery.post(
        ajaxurl,
        {
          action: "gscwpff_snooze_notice",
          key: key,
          security: jQuery("#gswpff-banner-ajax-nonce").val(),
        },
        function () {
          showNextSlide(currentSlide);
        },
      );
    },
  );
});

/** hide notice  */
jQuery(document).on("click", "#wpfpro-dismiss-header-notice", function () {
  var nonce = jQuery("#gs-ajax-nonce").val();

  jQuery("#pro-notice-bar").hide();

  jQuery.post(ajaxurl, {
    action: "dismiss_wpfpro_notice",
    nonce: nonce,
  });
});


/** notificatin slider arrow button will hide when slider is 1 or 0 */
jQuery(document).ready(function ($) {

  $(".wpforms-free-counter").each(function () {
    let $this = $(this);
    let countTo = parseFloat($this.attr("data-count"));

    $({ countNum: 0 }).animate(
      {
        countNum: countTo,
      },
      {
        duration: 2500,
        easing: "swing",

        step: function () {
          if (countTo % 1 !== 0) {
            $this.text(this.countNum.toFixed(1));
          } else {
            $this.text(Math.floor(this.countNum));
          }
        },

        complete: function () {
          if (countTo % 1 !== 0) {
            $this.text(countTo.toFixed(1));
          } else {
            $this.text(countTo);
          }
        },
      },
    );
  });
});





/** when the return auth with code will scroll down to token save button */
 jQuery(document).ready(function ($) {
  /* Check if URL has "code" parameter */
  const code = new URLSearchParams(window.location.search).get("code");

  if (!code) return;

  /*possible targets */
  const selectors = ["#save-wpform-gs-code"];

  let target = null;

  /* find which ID exists  */
  for (let sel of selectors) {
    if (document.querySelector(sel)) {
      target = sel;
      break;
    }
  }

  if (target) {
  
    window.location.hash = target.replace("#", "");

  
    document.querySelector(target).scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }
});


/***new slider for without permission for existing method */
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".gsc-slider-wrapper").forEach(function (wrapper) {
    const slider = wrapper.querySelector(".gsc-slider");
    const slides = wrapper.querySelectorAll(".gsc-slide");
    const prevBtn = wrapper.querySelector(".gsc-nav.prev");
    const nextBtn = wrapper.querySelector(".gsc-nav.next");
    let current = 0;
    function updateSlider() {
      slider.style.transform = "translateX(" + -current * 100 + "%)";
    }
    nextBtn?.addEventListener("click", function () {
      current = (current + 1) % slides.length;
      updateSlider();
    });
    prevBtn?.addEventListener("click", function () {
      current = (current - 1 + slides.length) % slides.length;
      updateSlider();
    });
    /*  attach function */
    wrapper.goToSlide = function (index) {
      current = index;
      updateSlider();
    };
  });

  /*  AUTO MOVE TO STEP 4   */
  setTimeout(function () {
    const errorBox = document.querySelector(".gscwpff-permission-error");
    const target = document.querySelector(".gscwpff-connection-guide-slider");
    if (!errorBox) {
      /* console.log("No permission error"); */
      return;
    }
    if (!target) {
      /* console.log("Slider not found"); */
      return;
    }
    if (target.goToSlide) {
      target.goToSlide(3);
      target.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
      console.log("Auto moved to Step 4");
    } else {
      console.log("goToSlide not available");
    }
  }, 800);
});


/*  Copy button  email address in service auth   */
jQuery(document).ready(function ($) {
  jQuery(document).on("click", ".gsc-connected-email", function (e) {
    e.preventDefault();

    var $btn = jQuery(this);
    var $parent = $btn.closest(".gsc-connected-email");

    var text = $parent.clone().children("a").remove().end().text().trim();
    if (!text) return;

    navigator.clipboard.writeText(text).then(function () {
      $parent.find(".gsc-copy-msg").remove();

      var msgDiv = jQuery(
        '<div class="position-relative-copy"><div class="gsc-copy-msg">Copied successfully</div></div>',
      );

      $parent.append(msgDiv);

      setTimeout(function () {
        msgDiv.fadeOut(300, function () {
          jQuery(this).remove();
        });
      }, 1500);
    });
  });
});

jQuery(function ($) {
  $(document).on("click", "#gscwpff-gsc-copy-error-logs", function (e) {
    e.preventDefault();
    console.log('Button clicked successfully!');

    const msgDiv = $(".gsc-copy-msg");
    const tbody = $(".error-log-table tbody");

    if (!tbody.length) {
      msgDiv.text("No logs table found").removeClass("d-none");
      return;
    }

    const rows = tbody.find("tr");
    if (!rows.length) {
      msgDiv.text("No logs found to copy.").removeClass("d-none");
      return;
    }

    let output = "";
    rows.each(function () {
      const cols = $(this).find("td");
      if (cols.length < 5) return;

      const date = cols.eq(0).text().trim();
      const errorId = cols.eq(1).text().trim();
      const code = cols.eq(2).text().trim();
      const message = cols.eq(3).text().trim();
      
      // Clean up details - remove extra spaces and newlines
      const detailsText = cols.eq(4).find("pre").text() || cols.eq(4).text();
      const details = detailsText
        .trim()
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0) // Remove empty lines
        .join('\n');

      output += `Date: ${date}\n`;
      output += `Error ID: ${errorId}\n`;
      output += `Code: ${code}\n`;
      output += `Message: ${message}\n`;
      output += `Details: ${details}\n`;
      output += `\n==========================\n\n`;
    });

    // Copy logic
    if (navigator.clipboard) {
      navigator.clipboard.writeText(output)
        .then(() => {
          msgDiv.text("Copied successfully").removeClass("d-none");
        })
        .catch(() => fallbackCopy(output));
    } else {
      fallbackCopy(output);
    }

    function fallbackCopy(text) {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.style.position = "fixed";
      ta.style.left = "-999999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
      msgDiv.text("Copied successfully").removeClass("d-none");
    }

    setTimeout(() => msgDiv.addClass("d-none"), 2000);
  });
});




/* jquery for save DB setting  start */
jQuery(document).ready(function ($) {
  const $toggle = $("#gsc_wpforms_db_enabled");
  const $saveBtn = $(".gscwpfp-db-storage");


   /*  Page load → disable button */
  $saveBtn.prop("disabled", true).addClass("common-disable");

   /*  Toggle change → enable button */
  $toggle.on("change", function () {
    $saveBtn.prop("disabled", false).removeClass("common-disable");
  });
});



jQuery(document).on("click",".gscwpfp-db-storage",function() {
  jQuery(".loading-uninstall-free").addClass("loading");
});

jQuery(document).ready(function ($) {
    // Disable all options except the first one
    jQuery("#bulk-action-selector-top option:not(:first)").attr("disabled", "disabled");
    jQuery("#bulk-action-selector-bottom option:not(:first)").attr("disabled", "disabled");
    
});

jQuery(document).ready(function ($) {
    jQuery("#doaction").prop("disabled", true);
    jQuery("#doaction2").prop("disabled", true);
    
});


jQuery(document).ready(function ($) {
  $(document).on("click", "#gscwpff-free-csv", function (e) {
    e.preventDefault();

    $("#gscwpff-free-pro-csv").removeClass("d-none");
  });


  $(document).on("click", ".gsc-pro-close", function (e) {
    e.preventDefault();

    $("#gscwpff-free-pro-csv").addClass("d-none");
  });
  

});

jQuery(document).ready(function ($) {
  $(document).on("click", ".gscwpff-send-to-sheet", function (e) {
    e.preventDefault();

    $("#gscwpff-send-csv-free-pro").removeClass("d-none");
  });

  $(document).on("click", ".gscwpff-sts-pro-close", function (e) {
    e.preventDefault();

    $("#gscwpff-send-csv-free-pro").addClass("d-none");
  });

  jQuery(document).ready(function ($) {
    // Disable all checkboxes in check-column
    $('.check-column input[type="checkbox"]').prop('disabled', true);
});

});


/* jquery for save DB setting end */



jQuery(document).ready(function ($) {
    function wpformdbLoadFeedPage(page) {
      var data = {
        action: "gscwpfrom_paginate_feed_list",
        paged: page,
        security: $("#wpformdb-ajax-nonce-pagination").val(),
      };

      $("#wpformdb-feed-table-body").html(
        '<tr class="wpformdb-feed-loading-row">' +
          '<td colspan="3">' +
            '<span class="wpformdb-loader"></span>' +
            '<span class="wpformdb-loader-text">Loading feeds...</span>' +
          "</td>" +
        "</tr>",
      );

      if($("#wpformdb-ajax-nonce-pagination").val()){

        $.post(ajaxurl, data, function (res) {
          if (res.success) {
            $("#wpformdb-feed-table-body").html(res.data.rows_html);
            $("#wpformdb-pagination-wrap").html(res.data.pagination_html);
            $("#wpformdb-feed-table").attr("data-page", page);

            // Hide the table header when no feed is connected (empty state).
            var dividbNoFeeds =
              $("#wpformdb-feed-table-body").find(".wpformdb-feed-empty").length > 0;
            $("#wpformdb-feed-table thead").toggle(!dividbNoFeeds);
          } else {
            $("#wpformdb-feed-table-body").html(
              "<tr><td colspan='3'>" +
                (res.data && res.data.error
                  ? res.data.error
                  : "Error loading feeds") +
                "</td></tr>",
            );
          }
        });
      }


    }

    wpformdbLoadFeedPage(1);

    $(document).on("click", ".wpformdb-page-link", function () {
      var page = $(this).data("page");
      wpformdbLoadFeedPage(page);
    });
});