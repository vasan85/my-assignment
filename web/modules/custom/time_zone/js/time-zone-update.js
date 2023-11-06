(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.timeZoneAjax = {
      attach: function (context) {
        // Function to update the time on the page.
        function updateTime(hours, minutes) {
          var ampm = hours >= 12 ? 'PM' : 'AM';

          // Convert hours to 12-hour format.
          hours = hours % 12;
          hours = hours ? hours : 12; // "0" should be displayed as "12".

          // Format hours and minutes with leading zeros.
          var formattedTime = hours + ':' + minutes + ' ' + ampm;

          // Update the time displayed on the page.
          $('.time-zone .time').text(formattedTime);
        }

        // Function to make an Ajax request to get the updated time.
        function fetchUpdatedTime() {
          // Make an Ajax request to the defined URL.
          $.ajax({
            url: '/time-zone-ajax', // Adjust the URL as per your routing configuration.
            dataType: 'json',
            success: function (data) {
              // Update the time using the received data.
              updateTime(data.hour, data.minute);

              // Schedule the next update after 60 seconds (60000 milliseconds).
              setTimeout(fetchUpdatedTime, 60000);
            },
            error: function (error) {
              console.error('Error fetching updated time: ' + error.statusText);
              // Retry the request after 5 seconds on error.
              setTimeout(fetchUpdatedTime, 5000);
            }
          });
        }

        // Initially fetch and display the time.
        fetchUpdatedTime();
      }
    };
  })(jQuery, Drupal, drupalSettings);
