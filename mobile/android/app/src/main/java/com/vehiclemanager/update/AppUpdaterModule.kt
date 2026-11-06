package com.vehiclemanager.update

import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import java.io.File

/**
 * Self-update for sideloaded builds: download a release APK and hand it to
 * the Android package installer. Android itself enforces that the APK has the
 * same package name and signing certificate and a higher versionCode.
 */
class AppUpdaterModule(private val ctx: ReactApplicationContext) : ReactContextBaseJavaModule(ctx) {

  override fun getName() = "AppUpdater"

  @ReactMethod
  fun getVersion(promise: Promise) {
    try {
      val info = ctx.packageManager.getPackageInfo(ctx.packageName, 0)
      val code = if (Build.VERSION.SDK_INT >= 28) info.longVersionCode else @Suppress("DEPRECATION") info.versionCode.toLong()
      promise.resolve(Arguments.createMap().apply {
        putString("versionName", info.versionName)
        putDouble("versionCode", code.toDouble())
      })
    } catch (e: Exception) {
      promise.reject("E_VERSION", e)
    }
  }

  /** True if the user has allowed this app to install packages (Android 8+). */
  @ReactMethod
  fun canInstall(promise: Promise) {
    promise.resolve(Build.VERSION.SDK_INT < 26 || ctx.packageManager.canRequestPackageInstalls())
  }

  @ReactMethod
  fun openInstallPermissionSettings(promise: Promise) {
    try {
      val intent = Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:${ctx.packageName}"))
        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
      ctx.startActivity(intent)
      promise.resolve(true)
    } catch (e: Exception) {
      promise.reject("E_SETTINGS", e)
    }
  }

  /** Download [url] with DownloadManager, then launch the installer. Resolves when the installer is shown. */
  @ReactMethod
  fun downloadAndInstall(url: String, fileName: String, promise: Promise) {
    if (!url.startsWith("https://")) {
      promise.reject("E_URL", "Only https downloads are allowed")
      return
    }
    try {
      val dir = ctx.getExternalFilesDir("updates") ?: File(ctx.filesDir, "updates")
      dir.mkdirs()
      dir.listFiles()?.forEach { it.delete() } // drop older downloads
      val safeName = fileName.replace(Regex("[^A-Za-z0-9._-]"), "_")
      val target = File(dir, safeName)

      val dm = ctx.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
      val request = DownloadManager.Request(Uri.parse(url))
        .setTitle("Vehicle Manager update")
        .setMimeType("application/vnd.android.package-archive")
        .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE)
        .setDestinationUri(Uri.fromFile(target))
      val id = dm.enqueue(request)

      val receiver = object : BroadcastReceiver() {
        override fun onReceive(c: Context, intent: Intent) {
          if (intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1) != id) return
          ctx.unregisterReceiver(this)
          val ok = dm.query(DownloadManager.Query().setFilterById(id)).use { cur ->
            cur.moveToFirst() && cur.getInt(cur.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS)) == DownloadManager.STATUS_SUCCESSFUL
          }
          if (!ok || !target.exists()) {
            promise.reject("E_DOWNLOAD", "Download failed")
            return
          }
          try {
            val uri = FileProvider.getUriForFile(ctx, "${ctx.packageName}.updates", target)
            val install = Intent(Intent.ACTION_VIEW)
              .setDataAndType(uri, "application/vnd.android.package-archive")
              .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
            ctx.startActivity(install)
            promise.resolve(true)
          } catch (e: Exception) {
            promise.reject("E_INSTALL", e)
          }
        }
      }
      ContextCompat.registerReceiver(
        ctx, receiver, IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE), ContextCompat.RECEIVER_EXPORTED
      )
    } catch (e: Exception) {
      promise.reject("E_DOWNLOAD", e)
    }
  }
}
