const firebaseConfig = {
  apiKey: "API_KEY",
  authDomain: "qooqz-2011.firebaseapp.com",
  projectId: "qooqz-2011",
  messagingSenderId: "724587252286",
  appId: "1:724587252286:web:71de19d19446a960c6df6f"
};

firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

Notification.requestPermission().then(permission => {
    if (permission === "granted") {
        messaging.getToken().then(token => {

            fetch("/api/notifications/save_token", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    token: token
                })
            });

        });
    }
});