<!DOCTYPE html>
<html>
<head>
    <title>Simple Chatbot</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

</head>
<body class="bg-gray-100">
<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-3xl font-bold mb-6 text-purple-500">Chatbot</h1>

    <div id="chat-container" class="bg-white rounded-lg shadow-md p-4 mb-4 h-96 overflow-y-auto">
        <div class="chat-message bot mb-4">
            <div class="message-content bg-blue-100 p-3 rounded-lg">
                Hello! How can I assist you today?
            </div>
        </div>
    </div>

    <form id="chat-form" class="flex">
        <input type="text" id="message-input"
               class="flex-grow p-3 border border-purple-400 rounded-l-lg focus:outline-none"
               placeholder="Type your message..." autocomplete="off">
        <button type="submit"
                class="bg-purple-600 text-white px-6 py-3 rounded-r-lg hover:bg-purple-700 transition">
            Send
        </button>
    </form>
    <small class="text-gray-400">*Chatbot can make mistake</small>
</div>

<script>
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message-input');
    const chatContainer = document.getElementById('chat-container');
    let chatHistory = [];

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = messageInput.value.trim();

        if (!message) return;

        // Add user message to UI
        addMessageToUI(message, 'user');
        chatHistory.push({ role: 'user', parts: [{ text: message }] });
        messageInput.value = '';

        // Create bot message element
        const botMessageElement = createBotMessageElement();

        try {
            const response = await fetch('/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    message: message,
                    history: chatHistory
                })
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");
            let responseText = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                const chunk = decoder.decode(value, {stream: true});
                const lines = chunk.split('\n\n').filter(Boolean);

                for (const line of lines) {
                    if (line.startsWith('data:')) {
                        const data = JSON.parse(line.slice(5).trim());
                        responseText += data.text;
                        botMessageElement.querySelector('.message-content').innerHTML = marked.parse(responseText);
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    }
                }
            }

            // Save final response to history
            chatHistory.push({ role: 'model', parts: [{ text: responseText }] });

            console.log(JSON.stringify(chatHistory))

        } catch (error) {
            console.error('Error:', error);
            botMessageElement.querySelector('.message-content').textContent =
                "Sorry, something went wrong. Please try again.";
        }
    });

    function addMessageToUI(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.className = `chat-message ${sender} mb-4`;

        const contentElement = document.createElement('div');
        contentElement.className = sender === 'user' ?
            'message-content bg-gray-100 p-3 rounded-lg text-right' :
            'message-content bg-blue-100 p-3 rounded-lg';

        contentElement.textContent = message;
        messageElement.appendChild(contentElement);
        chatContainer.appendChild(messageElement);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function createBotMessageElement() {
        const messageElement = document.createElement('div');
        messageElement.className = 'chat-message bot mb-4';

        const contentElement = document.createElement('div');
        contentElement.className = 'message-content bg-blue-100 p-3 rounded-lg';
        contentElement.textContent = '...';

        messageElement.appendChild(contentElement);
        chatContainer.appendChild(messageElement);
        chatContainer.scrollTop = chatContainer.scrollHeight;

        return messageElement;
    }
</script>
</body>
</html>
