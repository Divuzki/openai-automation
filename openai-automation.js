jQuery(document).ready(function ($) {
  // Function to show notification
  function showNotification(message, type = "success") {
    // Remove any existing notifications
    $(".openai-notification").remove();

    // Create notification element
    var notification = $(
      '<div class="openai-notification ' + type + '">' + message + "</div>"
    );

    // Add to container
    $(".openai-summary-container").prepend(notification);

    // Auto-remove after 4 seconds
    setTimeout(function () {
      notification.addClass("fade-out");
      setTimeout(function () {
        notification.remove();
      }, 300);
    }, 4000);
  }

  // Function to handle summary generation
  function generateSummary() {
    var post_id = $("#post_ID").val();

    // Show loading indicator with animation
    $("#summary-loading").fadeIn(300);
    $("#summary-content").addClass("loading-state");
    $("#generate-summary, #regenerate-summary").prop("disabled", true);

    $.ajax({
      url: openai_automation_vars.ajax_url,
      type: "POST",
      data: {
        action: "generate_openai_summary",
        post_id: post_id,
        nonce: openai_automation_vars.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Update content with animation
          $("#summary-content")
            .removeClass("empty-summary")
            .addClass("has-summary")
            .html("<p class='fade-in'>" + response.data + "</p>");

          // Add regenerate button if it doesn't exist
          if ($("#regenerate-summary").length === 0) {
            var regenerateBtn = $(
              '<button id="regenerate-summary" class="button fade-in"><span class="button-icon dashicons dashicons-update"></span>Regenerate Summary</button>'
            );

            $("#generate-summary").after(regenerateBtn);

            // Add click handler for the newly added button
            regenerateBtn.on("click", function (e) {
              e.preventDefault();
              generateSummary();
            });
          }

          // Show success notification
          showNotification("Summary generated successfully!", "success");
        } else {
          // Show error notification instead of alert
          showNotification("Error: " + response.data, "error");
        }
      },
      error: function (xhr, status, error) {
        // Show error notification instead of alert
        showNotification("Error: " + error, "error");
      },
      complete: function () {
        // Hide loading indicator when complete with animation
        $("#summary-loading").fadeOut(300);
        $("#summary-content").removeClass("loading-state");
        $("#generate-summary, #regenerate-summary").prop("disabled", false);
      },
    });
  }

  // Generate summary button click handler with animation
  $("#generate-summary").on("click", function (e) {
    e.preventDefault();
    $(this).addClass("button-clicked");
    setTimeout(() => $(this).removeClass("button-clicked"), 300);
    generateSummary();
  });

  // Regenerate summary button click handler with animation
  $(document).on("click", "#regenerate-summary", function (e) {
    e.preventDefault();
    $(this).addClass("button-clicked");
    setTimeout(() => $(this).removeClass("button-clicked"), 300);
    generateSummary();
  });
});
