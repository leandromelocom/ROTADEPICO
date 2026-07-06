package br.com.rotadepico.companion.data

import android.content.Context
import android.provider.Settings

class SettingsRepository(private val context: Context) {

    private val preferences = context.getSharedPreferences("rotadepico_companion", Context.MODE_PRIVATE)

    fun save(apiBaseUrl: String, bearerToken: String, deviceId: String) {
        preferences.edit()
            .putString(KEY_API_BASE_URL, apiBaseUrl.trim().trimEnd('/'))
            .putString(KEY_BEARER_TOKEN, bearerToken.trim())
            .putString(KEY_DEVICE_ID, deviceId.trim())
            .apply()
    }

    fun saveAuthSession(apiBaseUrl: String, bearerToken: String, deviceId: String, userName: String, userEmail: String) {
        preferences.edit()
            .putString(KEY_API_BASE_URL, apiBaseUrl.trim().trimEnd('/'))
            .putString(KEY_BEARER_TOKEN, bearerToken.trim())
            .putString(KEY_DEVICE_ID, deviceId.trim())
            .putString(KEY_USER_NAME, userName.trim())
            .putString(KEY_USER_EMAIL, userEmail.trim())
            .apply()
    }

    fun apiBaseUrl(): String = preferences.getString(KEY_API_BASE_URL, DEFAULT_API_BASE_URL).orEmpty()

    fun bearerToken(): String = preferences.getString(KEY_BEARER_TOKEN, "").orEmpty()

    fun deviceId(): String = preferences.getString(KEY_DEVICE_ID, androidId()).orEmpty()

    fun userName(): String = preferences.getString(KEY_USER_NAME, "").orEmpty()

    fun userEmail(): String = preferences.getString(KEY_USER_EMAIL, "").orEmpty()

    fun hasNotificationAccess(): Boolean {
        val enabled = Settings.Secure.getString(
            context.contentResolver,
            "enabled_notification_listeners"
        ).orEmpty()

        return enabled.contains(context.packageName)
    }

    private fun androidId(): String {
        return Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)
            ?: "android-device"
    }

    companion object {
        private const val KEY_API_BASE_URL = "api_base_url"
        private const val KEY_BEARER_TOKEN = "bearer_token"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_USER_NAME = "user_name"
        private const val KEY_USER_EMAIL = "user_email"
        private const val DEFAULT_API_BASE_URL = "https://rotadepico.mpncloud.com.br"
    }
}
