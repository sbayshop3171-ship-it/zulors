package com.zulors.app;

import android.content.Context;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.telecom.Connection;
import android.telecom.DisconnectCause;
import android.telecom.PhoneAccount;
import android.telecom.TelecomManager;
import android.telecom.VideoProfile;
import android.util.Log;

public class ZulorsTelecomConnection extends Connection {
    private static final String TAG = "ZulorsTelecomConn";
    private final Context appContext;
    private final Bundle callBundle;
    private final String callId;
    private boolean answered;
    private boolean destroyed;

    public ZulorsTelecomConnection(Context context, Bundle callBundle) {
        this.appContext = context.getApplicationContext();
        this.callBundle = callBundle == null ? new Bundle() : new Bundle(callBundle);
        this.callId = ZulorsTelecomCallManager.callId(this.callBundle);

        setAddress(buildAddress(), TelecomManager.PRESENTATION_ALLOWED);
        setCallerDisplayName(displayName(), TelecomManager.PRESENTATION_ALLOWED);
        setAudioModeIsVoip(true);
        setVideoState(VideoProfile.STATE_AUDIO_ONLY);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            setConnectionProperties(Connection.PROPERTY_SELF_MANAGED);
        }

        setInitialized();
        setRinging();
    }

    @Override
    public void onShowIncomingCallUi() {
        Log.i(TAG, "Suppressed custom incoming call UI; relying on notification-only flow. callId=" + callId);
    }

    @Override
    public void onAnswer() {
        ZulorsTelecomCallManager.answerCall(appContext, callBundle, true);
    }

    @Override
    public void onAnswer(int videoState) {
        onAnswer();
    }

    @Override
    public void onReject() {
        ZulorsTelecomCallManager.declineCall(appContext, callBundle);
    }

    @Override
    public void onDisconnect() {
        if (answered) {
            markDisconnectedFromApp(DisconnectCause.LOCAL);
        }
        else {
            ZulorsTelecomCallManager.declineCall(appContext, callBundle);
        }
    }

    @Override
    public void onAbort() {
        ZulorsTelecomCallManager.declineCall(appContext, callBundle);
    }

    public void markAnsweredFromApp() {
        if (destroyed) {
            return;
        }

        answered = true;
        setActive();
    }

    public void markDisconnectedFromApp(int cause) {
        if (destroyed) {
            return;
        }

        destroyed = true;
        setDisconnected(new DisconnectCause(cause));
        destroy();
        ZulorsTelecomCallManager.unregisterConnection(callId);
    }

    private Uri buildAddress() {
        String id = firstNonBlank(callId, "zulors");
        String sanitized = id.replaceAll("[^0-9A-Za-z.+_-]", "");

        if (sanitized.trim().isEmpty()) {
            sanitized = "zulors";
        }

        return Uri.fromParts(PhoneAccount.SCHEME_SIP, sanitized + "@zulors.com", null);
    }

    private String displayName() {
        return firstNonBlank(
            callBundle.getString(ZulorsTelecomCallManager.EXTRA_CALLER_NAME),
            callBundle.getString(ZulorsTelecomCallManager.EXTRA_TITLE),
            "Zulors"
        );
    }

    private String firstNonBlank(String... values) {
        if (values == null) {
            return null;
        }

        for (String value : values) {
            if (value != null && !value.trim().isEmpty()) {
                return value;
            }
        }

        return null;
    }
}
