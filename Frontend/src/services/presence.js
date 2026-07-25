import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { reactive } from "vue";

const backendOrigin = "";
const channelName = "clinic.online";

export const presenceState = reactive({
  users: [],
  connected: false,
  connecting: false,
  error: "",
});

let echo = null;
let activeUserId = null;

const normalizeUser = (user) => ({
  id: Number(user.id),
  name: String(user.name || "کاربر"),
  avatar_url: user.avatar_url || null,
  roles: Array.isArray(user.roles) ? user.roles : [],
});

const setUsers = (users) => {
  const unique = new Map();
  users.map(normalizeUser).forEach((user) => unique.set(user.id, user));
  presenceState.users = [...unique.values()].sort((a, b) =>
    a.name.localeCompare(b.name, "fa"),
  );
};

const addUser = (user) => setUsers([...presenceState.users, user]);
const removeUser = (user) => {
  const id = Number(user?.id);
  presenceState.users = presenceState.users.filter((item) => item.id !== id);
};

export function startPresence(user) {
  if (!user?.id) return;

  if (activeUserId === Number(user.id) && echo) {
    const connection = echo.connector?.pusher?.connection;
    if (connection?.state === "connected") {
      presenceState.connected = true;
      presenceState.connecting = false;
      return;
    }

    echo.connect();
    presenceState.connecting = true;
    return;
  }

  stopPresence();

  activeUserId = Number(user.id);
  presenceState.connecting = true;
  presenceState.error = "";
  window.Pusher = Pusher;

  const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(":", "");
  const secure = scheme === "https";
  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;

  echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: secure,
    enabledTransports: ["ws", "wss"],
    disableStats: true,
    authorizer: (channel) => ({
      authorize(socketId, callback) {
        axios.post(`${backendOrigin}/broadcasting/auth`, {
          socket_id: socketId,
          channel_name: channel.name,
        }).then(({ data }) => callback(false, data))
          .catch((error) => callback(true, error));
      },
    }),
  });

  const connection = echo.connector.pusher.connection;
  presenceState.connected = connection.state === "connected";
  presenceState.connecting = ["initialized", "connecting", "unavailable"].includes(connection.state);
  connection.bind("state_change", ({ current }) => {
    presenceState.connected = current === "connected";
    presenceState.connecting = ["connecting", "unavailable"].includes(current);
    if (current === "connected") presenceState.error = "";
    if (["disconnected", "failed"].includes(current)) setUsers([]);
  });

  echo.join(channelName)
    .here((users) => {
      setUsers(users);
      presenceState.connected = true;
      presenceState.connecting = false;
      presenceState.error = "";
    })
    .joining(addUser)
    .leaving(removeUser)
    .error((error) => {
      presenceState.connecting = false;
      presenceState.error = error?.response?.data?.message
        || error?.message
        || `HTTP ${error?.response?.status || "unknown"}`;
    });
}

export function stopPresence() {
  if (echo) {
    echo.leave(channelName);
    echo.disconnect();
  }
  echo = null;
  activeUserId = null;
  presenceState.connected = false;
  presenceState.connecting = false;
  presenceState.error = "";
  setUsers([]);
}
