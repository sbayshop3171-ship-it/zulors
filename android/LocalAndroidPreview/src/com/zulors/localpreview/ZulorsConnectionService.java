package com.zulors.app;

import android.os.Bundle;
import android.telecom.Connection;
import android.telecom.ConnectionRequest;
import android.telecom.ConnectionService;
import android.telecom.DisconnectCause;
import android.telecom.PhoneAccountHandle;
import android.util.Log;

public class ZulorsConnectionService extends ConnectionService {
    private static final String TAG = "ZulorsConnectionSvc";

    @Override
    public void onCreate() {
        super.onCreate();
        ZulorsTelecomCallManager.registerPhoneAccount(this);
    }

    @Override
    public Connection onCreateIncomingConnection(PhoneAccountHandle connectionManagerPhoneAccount, ConnectionRequest request) {
        Bundle callBundle = ZulorsTelecomCallManager.extractCallBundle(
            request != null ? request.getExtras() : null,
            this
        );
        ZulorsTelecomConnection connection = new ZulorsTelecomConnection(this, callBundle);

        ZulorsTelecomCallManager.registerConnection(
            ZulorsTelecomCallManager.callId(callBundle),
            connection
        );

        return connection;
    }

    @Override
    public void onCreateIncomingConnectionFailed(PhoneAccountHandle connectionManagerPhoneAccount, ConnectionRequest request) {
        String callId = ZulorsTelecomCallManager.callId(
            ZulorsTelecomCallManager.extractCallBundle(request != null ? request.getExtras() : null, this)
        );
        Log.w(TAG, "Telecom rejected incoming call UI request; keeping notification-only fallback alive. callId=" + callId);
    }

    @Override
    public Connection onCreateOutgoingConnection(PhoneAccountHandle connectionManagerPhoneAccount, ConnectionRequest request) {
        Connection connection = Connection.createFailedConnection(new DisconnectCause(DisconnectCause.ERROR));
        connection.setDisconnected(new DisconnectCause(DisconnectCause.ERROR, "Outgoing Telecom calls are handled by Zulors UI."));

        return connection;
    }
}
