package br.com.rotadepico.companion.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.graphics.PixelFormat
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.view.Gravity
import android.view.LayoutInflater
import android.view.WindowManager
import android.widget.TextView
import androidx.core.app.NotificationCompat
import br.com.rotadepico.companion.R
import br.com.rotadepico.companion.model.OfferDecisionResponse

class DecisionOverlayPresenter(private val context: Context) {

    fun show(decision: OfferDecisionResponse) {
        val overlay = decision.overlay

        if (overlay != null && overlay.show) {
            showOverlay(
                headline = overlay.headline,
                message = overlay.message,
                tone = overlay.tone,
                dismissAfterMs = overlay.dismissAfterMs
            )
        }

        showHeadsUpNotification(
            title = decision.pushNotification?.title ?: context.getString(R.string.overlay_default_title),
            body = decision.pushNotification?.body ?: context.getString(R.string.overlay_default_message)
        )
    }

    private fun showOverlay(headline: String, message: String, tone: String, dismissAfterMs: Long) {
        val windowManager = context.getSystemService(Context.WINDOW_SERVICE) as WindowManager
        val inflater = LayoutInflater.from(context)
        val view = inflater.inflate(R.layout.overlay_decision, null)

        view.setBackgroundResource(
            when (tone) {
                "positive" -> R.drawable.overlay_positive
                "warning" -> R.drawable.overlay_warning
                else -> R.drawable.overlay_danger
            }
        )

        view.findViewById<TextView>(R.id.overlayHeadline).text = headline
        view.findViewById<TextView>(R.id.overlayMessage).text = message

        val params = WindowManager.LayoutParams(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.WRAP_CONTENT,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
            PixelFormat.TRANSLUCENT
        ).apply {
            gravity = Gravity.TOP
            y = 64
        }

        try {
            windowManager.addView(view, params)
            Handler(Looper.getMainLooper()).postDelayed({
                try {
                    windowManager.removeView(view)
                } catch (_: Exception) {
                }
            }, dismissAfterMs)
        } catch (_: Exception) {
        }
    }

    private fun showHeadsUpNotification(title: String, body: String) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val channelId = "rotadepico_decisions"

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            manager.createNotificationChannel(
                NotificationChannel(
                    channelId,
                    context.getString(R.string.decision_channel_name),
                    NotificationManager.IMPORTANCE_HIGH
                )
            )
        }

        val notification = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .build()

        manager.notify((System.currentTimeMillis() % Int.MAX_VALUE).toInt(), notification)
    }
}
