jQuery(document).ready(function ($) {
    // Toggle open
    $("#ai-chatbot-toggle").on("click", function () {
        $("#ai-chatbot").fadeIn();
        $(this).hide();
    });

    // Close button
    $("#ai-chatbot-close").on("click", function () {
        $("#ai-chatbot").fadeOut();
        $("#ai-chatbot-toggle").show();
    });

    // Send message
    $("#ai-chatbot-send").on("click", function () {
        sendMessage();
    });

    $("#ai-chatbot-text").keypress(function (e) {
        if (e.which === 13) {
            sendMessage();
            return false;
        }
    });

    function sendMessage() {
        let msg = $("#ai-chatbot-text").val().trim();
        if (!msg) return;

        $("#ai-chatbot-messages").append("<div><b>You:</b> " + msg + "</div>");
        $("#ai-chatbot-text").val("");

        $.post(aiChatbot.ajax_url, {
            action: "ai_chatbot_send",
            message: msg,
            nonce: aiChatbot.nonce
        }, function (res) {
            $("#ai-chatbot-messages").append("<div><b>Bot:</b> " + res.reply + "</div>");
            $("#ai-chatbot-messages").scrollTop($("#ai-chatbot-messages")[0].scrollHeight);
        });
    }
});
