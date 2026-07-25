jQuery(document).ready(function ($) {
  // Define globally accessible functions
  window.copySystemInfo = function () {
    const $ = jQuery;
    const $container = $(".info-container");
    let systemInfoText = "";

    // Loop through each section
    $container.find(".info-button").each(function () {
        const $button = $(this);
        const $infoContent = $button.closest(".mb-20, .info-container > div").next(".info-content");
        
        // Get heading text
        const heading = $button
            .clone()
            .find(".dashicons")
            .remove()
            .end()
            .text()
            .trim();

        if (heading && $infoContent.length) {
            // Add section heading
            systemInfoText += "========================================\n";
            systemInfoText += heading + "\n";
            systemInfoText += "========================================\n";

            // Get table rows
            $infoContent.find("table tbody tr").each(function () {
                const $cells = $(this).find("td");
                
                if ($cells.length >= 2) {
                    const label = $cells.eq(0).text().trim();
                    const value = $cells.eq(1).text().trim();

                    if (label && value) {
                        systemInfoText += `${label}: ${value}\n`;
                    }
                } else if ($cells.length === 1) {
                    // For single column rows
                    const text = $cells.eq(0).text().trim();
                    if (text) {
                        systemInfoText += `${text}\n`;
                    }
                }
            });

            // Get paragraph text if any
            $infoContent.find("p").each(function () {
                const text = $(this).text().trim();
                if (text) {
                    systemInfoText += `${text}\n`;
                }
            });

            systemInfoText += "\n\n";
        }
    });

    // Copy to clipboard
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(systemInfoText.trim())
            .then(function () {
                $(".gsc-copy-msg")
                    .stop(true, true)
                    .removeClass("d-none")
                    .fadeIn(200)
                    .delay(2000)
                    .fadeOut(300);
            })
            .catch(function (err) {
                console.error("Unable to copy system info:", err);
                fallbackCopy(systemInfoText.trim());
            });
    } else {
        fallbackCopy(systemInfoText.trim());
    }

    function fallbackCopy(text) {
        const ta = document.createElement("textarea");
        ta.value = text;
        ta.style.position = "fixed";
        ta.style.left = "-999999px";
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand("copy");
            $(".gsc-copy-msg")
                .stop(true, true)
                .removeClass("d-none")
                .fadeIn(200)
                .delay(2000)
                .fadeOut(300);
        } catch (err) {
            console.error("Copy failed:", err);
        }
        document.body.removeChild(ta);
    }
};



  window.copyErrorLog = function () {
    const $textarea = $(".errorlog");
    const $copyMessage = $(".copy-message");

    if ($textarea.length && $copyMessage.length) {
      $textarea.select();
      try {
        document.execCommand("copy");
        $copyMessage.show();
        setTimeout(() => $copyMessage.hide(), 3000);
      } catch (err) {
        console.error("Unable to copy error log:", err);
        alert("Error log copy failed. Please copy it manually.");
      }
      $textarea.blur();
    } else {
      alert("Error log textarea or copy message not found.");
    }
  };

  // Clear Error Log
  function clearErrorLog() {
    $(".errorlog").val("");
  }

  /** js for system status start  */
  $("#info-container").show();
  function accordionToggle(button, container) {
    $(button).on("click", function () {
      if ($(container).is(":visible")) {
        // second click → close same section
        $(container).slideUp();
      } else {
        // open clicked, close others
        $(".info-content").slideUp();
        $(container).slideDown();
      }
    });
  }

  accordionToggle("#gscwpff-show-info-button", "#info-container");
  accordionToggle(
    "#gscwpff-show-wordpress-info-button",
    "#wordpress-info-container",
  );
  accordionToggle("#gscwpff-show-Drop-info-button", "#Drop-info-container");
  accordionToggle("#gscwpff-show-active-info-button", "#active-info-container");
  accordionToggle(
    "#gscwpff-show-netplug-info-button",
    "#netplug-info-container",
  );
  accordionToggle("#gscwpff-show-acplug-info-button", "#acplug-info-container");
  accordionToggle("#gscwpff-show-server-info-button", "#server-info-container");
  accordionToggle(
    "#gscwpff-show-database-info-button",
    "#database-info-container",
  );
  accordionToggle("#gscwpff-show-wrcons-info-button", "#wrcons-info-container");
  accordionToggle("#gscwpff-show-ftps-info-button", "#ftps-info-container");

  /** js for system status end  */

  $(".copy-error-log").on("click", function (e) {
    e.preventDefault();
    copyErrorLog();
  });

  $(".clear-content-logs-elemnt").on("click", function (e) {
    e.preventDefault();
    clearErrorLog();
  });

  /** Js for copy system status data  */
  jQuery(document).ready(function ($) {
    // Remove any existing handlers
    $(document).off("click", "#gscwpff-free-system-copy");
    
    // Attach click handler
    $(document).on("click", "#gscwpff-free-system-copy", function (e) {
        e.preventDefault();
        e.stopPropagation();
        window.copySystemInfo();
    });
  });
});



document.addEventListener("DOMContentLoaded", function () {
  /**
   * Copies the content of the error log textarea to the clipboard
   * and shows a temporary "Copied" confirmation message.
   */

  function copyErrorLog() {
    // Select the textarea containing the error log
    var textarea = document.querySelector(".errorlog");

    // Select the message div (button na niche no div)
    var copyMessage = document.querySelector(".gsc-copy-msg");

    if (textarea && copyMessage) {
      textarea.select();

      try {
        // Copy text
        document.execCommand("copy");

        // Show message
        copyMessage.classList.remove("d-none");

        // Hide message after 3 seconds
        setTimeout(function () {
          copyMessage.classList.add("d-none");
        }, 3000);
      } catch (err) {
        console.error("Unable to copy error log:", err);
      }

      textarea.blur();
    }
  }

  var copyButton = document.querySelector(".copy");

  if (copyButton) {
    copyButton.addEventListener("click", function (event) {
      event.preventDefault();
      copyErrorLog();
    });
  }
});

jQuery(document).ready(function ($) {
  $("#gscwpff-copy-logs-info").on("click", function (e) {
    e.preventDefault();

    var rows = $("table tbody tr");
    var copyText = "";

    if (!rows.length) {
      alert("No error logs found.");
      return;
    }

    rows.each(function () {
      var cols = $(this).find("td");

      if (cols.length >= 4) {
        copyText += $(cols[0]).text().trim() + "\n";
        copyText += $(cols[1]).text().trim() + "\n";
        copyText += $(cols[2]).text().trim() + "\n";
        copyText += $(cols[3]).text().trim() + "\n";
        copyText += "----------------------------------------\n\n";
      }
    });

    /* Temporary textarea copy */
    var tempTextarea = $("<textarea>");
    $("body").append(tempTextarea);
    tempTextarea.val(copyText).select();
    document.execCommand("copy");
    tempTextarea.remove();

    /*  Show success message */
    var $msg = $(".gsc-copy-msg");

    $msg.text("Copied successfully").removeClass("d-none");

    setTimeout(function () {
      $msg.addClass("d-none");
    }, 3000);
  });
});


jQuery(document).ready(function ($) {
  $("#gscwpff-csv-info").on("click", function (e) {
    e.preventDefault();

    var rows = $("table.widefat tr");
    var csvContent = "";

    if (rows.length === 0) {
      alert("No error logs found.");
      return;
    }

    rows.each(function () {
      var cols = $(this).find("th, td");
      var rowData = [];

      cols.each(function () {
        var text = $(this).text().trim();

        /*  Escape quotes */
        text = text.replace(/"/g, '""');

        rowData.push('"' + text + '"');
      });

      csvContent += rowData.join(",") + "\n";
    });

    /*  Create Blob */
    var blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });

    var link = document.createElement("a");
    var url = URL.createObjectURL(blob);

    link.setAttribute("href", url);
    link.setAttribute("download", "error-log.csv");
    link.style.visibility = "hidden";

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });
});

/**
 * Clear debug for system status tab
 */
jQuery(document).on("click", ".gscwpff-clear-content-logs", function () {
  jQuery(".gscwpff-clear-loading-sign-logs").addClass("loading");
  var data = {
    action: "gscwpff_clear_debug_logs",
    security: jQuery("#gscwpff-ajax-nonce").val(),
  };
  jQuery.post(ajaxurl, data, function (response) {
    if (response == -1) {
      return false; // Invalid nonce
    }

    if (response.success) {
      jQuery(".gscwpff-clear-loading-sign-logs").removeClass("loading");

      setTimeout(function () {
        location.reload();
      }, 1000);
    }
  });
});
