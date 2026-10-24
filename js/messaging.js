// Reply box behaviour for admin_messages.php and my_messages.php.

$(function () {
  var $newForm = $('#newMessageForm');
  if ($newForm.length) {
    var $newSubject = $('#newMessageSubject');
    var $newBody = $('#newMessageBody');
    var $newError = $('#newMessageError');

    $newForm.on('submit', function (e) {
      e.preventDefault();
      var subject = $newSubject.val().trim();
      var body = $newBody.val().trim();
      if (!subject || !body) return;

      var $button = $newForm.find('button[type="submit"]');
      $button.prop('disabled', true);
      $newError.addClass('d-none');

      $.ajax({
        url: 'start_conversation.php',
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ subject: subject, body: body })
      }).done(function (res) {
        if (res && res.success && res.conversation_id) {
          window.location.href = 'my_messages.php?id=' + encodeURIComponent(res.conversation_id);
        } else {
          $newError.text((res && res.error) || 'Could not send the message.').removeClass('d-none');
        }
      }).fail(function (xhr) {
        var message = 'Could not send the message.';
        try { message = JSON.parse(xhr.responseText).error || message; } catch (e) {}
        $newError.text(message).removeClass('d-none');
      }).always(function () {
        $button.prop('disabled', false);
      });
    });
  }

  var $card = $('#threadCard');
  if (!$card.length) return;

  var conversationId = $card.data('conversationId');
  var $form = $('#replyForm');
  var $body = $('#replyBody');
  var $err = $('#replyError');
  var $messages = $('#threadMessages');

  // Start scrolled to the newest message.
  $messages.scrollTop($messages[0].scrollHeight);

  function appendOwnMessage(text) {
    var when = new Date().toLocaleString(undefined, {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
    });
    var $row = $('<div class="d-flex mb-3 justify-content-end"></div>');
    var $bubble = $('<div class="p-2 rounded-3 bg-primary text-white" style="max-width:75%;"></div>');
    var $text = $('<div class="small"></div>');
    // textContent — never trust our own echo of what the user typed
    $text.text(text);
    var $meta = $('<div class="small text-white-50 mt-1"></div>').text('You · ' + when);
    $bubble.append($text).append($meta);
    $row.append($bubble);
    $messages.append($row);
    $messages.scrollTop($messages[0].scrollHeight);
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    var text = $body.val().trim();
    if (!text) return;

    var $btn = $form.find('button[type="submit"]');
    $btn.prop('disabled', true);
    $err.addClass('d-none');

    $.ajax({
      url: 'conversation_reply.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ conversation_id: conversationId, body: text })
    }).done(function (res) {
      if (res && res.success) {
        appendOwnMessage(text);
        $body.val('');
      } else {
        $err.text((res && res.error) || 'Could not send the message.').removeClass('d-none');
      }
    }).fail(function (xhr) {
      var msg = 'Could not send the message.';
      try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
      $err.text(msg).removeClass('d-none');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });
});
