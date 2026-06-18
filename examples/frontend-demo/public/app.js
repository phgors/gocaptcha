// examples/frontend-demo/public/app.js
// 配置表驱动四种验证码模式。每种模式首次切到 Tab 时懒创建实例并加载。
(function () {
  "use strict";

  var GoCaptcha = window.GoCaptcha;

  // 各模式配置：构造器、API 路径、组件 config、confirm→verify 转换
  var MODES = {
    "click": {
      ctor: GoCaptcha.Click,
      api: "/api/click",
      cfg: { width: 300, height: 220, thumbWidth: 150, thumbHeight: 40 },
      toBody: function (dots) {
        return { points: dots.map(function (d) { return { x: d.x, y: d.y }; }) };
      }
    },
    "slide": {
      ctor: GoCaptcha.Slide,
      api: "/api/slide",
      cfg: { width: 300, height: 220 },
      toBody: function (point) {
        return { x: point.x, y: point.y };
      }
    },
    "slide-region": {
      ctor: GoCaptcha.SlideRegion,
      api: "/api/slide-region",
      cfg: { width: 300, height: 220 },
      toBody: function (point) {
        return { x: point.x, y: point.y };
      }
    },
    "rotate": {
      ctor: GoCaptcha.Rotate,
      api: "/api/rotate",
      cfg: { width: 220, height: 220 },
      toBody: function (angle) {
        return { angle: angle };
      }
    }
  };

  var instances = {};   // type → 组件实例

  function $(sel, root) { return (root || document).querySelector(sel); }

  // 状态条
  function setStatus(type, status, text) {
    var span = $(".status", $("#panel-" + type));
    span.setAttribute("data-status", status);
    span.textContent = text;
  }

  // 请求生成接口并喂给组件
  function load(type) {
    var mode = MODES[type];
    setStatus(type, "loading", "加载中…");
    // 重置组件交互状态（清除已点击 dots / 滑块位置 / 旋转角度）
    if (instances[type] && typeof instances[type].clear === "function") {
      instances[type].clear();
    }
    return fetch(mode.api)
      .then(function (r) {
        if (!r.ok) throw new Error("生成接口 HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        instances[type].setData(data);
        instances[type].__meta = data;   // 缓存元数据（如需偏移换算可用）
        setStatus(type, "idle", guideOf(type));
      })
      .catch(function (err) {
        console.error("[" + type + "] load failed", err);
        setStatus(type, "error", "请求失败，请点刷新重试");
      });
  }

  function guideOf(type) {
    return {
      "click": "点击图中文字，完成后点确认",
      "slide": "拖动滑块，把拼图移到缺口",
      "slide-region": "拖动拼图到缺口位置",
      "rotate": "旋转缩略图，使其与主图对齐"
    }[type];
  }

  // 请求校验接口
  function verify(type, payload, reset) {
    var mode = MODES[type];
    setStatus(type, "loading", "校验中…");
    fetch(mode.api + "/verify", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(mode.toBody(payload, instances[type].__meta))
    })
      .then(function (r) {
        if (!r.ok) throw new Error("校验接口 HTTP " + r.status);
        return r.json();
      })
      .then(function (res) {
        if (res.ok) {
          setStatus(type, "success", "验证通过");
        } else {
          setStatus(type, "error", "验证失败，请重试");
          if (typeof reset === "function") reset();
        }
      })
      .catch(function (err) {
        console.error("[" + type + "] verify failed", err);
        setStatus(type, "error", "请求失败，请点刷新重试");
        if (typeof reset === "function") reset();
      });
  }

  // 绑定组件事件（按类型差异）
  function bindEvents(type, capt) {
    capt.setEvents({
      refresh: function () { load(type); },
      close: function () { /* 保留 */ },
      confirm: function (payload, reset) {
        console.log("[" + type + "] confirm payload:", payload);
        verify(type, payload, reset);
      }
    });
  }

  // 初始化某个模式（懒创建）
  function init(type) {
    if (instances[type]) {
      load(type);
      return;
    }
    var mode = MODES[type];
    var el = $("#captcha-" + type);
    var capt = new mode.ctor(mode.cfg);
    capt.mount(el);
    instances[type] = capt;
    bindEvents(type, capt);
    load(type);
  }

  // Tab 切换
  function activate(type) {
    document.querySelectorAll(".tab").forEach(function (t) {
      t.classList.toggle("active", t.dataset.type === type);
    });
    document.querySelectorAll(".panel").forEach(function (p) {
      p.classList.toggle("active", p.id === "panel-" + type);
    });
    init(type);
  }

  // 绑定 Tab 与刷新按钮
  document.querySelectorAll(".tab").forEach(function (t) {
    t.addEventListener("click", function () { activate(t.dataset.type); });
  });
  document.querySelectorAll(".refresh").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var type = btn.closest(".panel").id.replace("panel-", "");
      load(type);
    });
  });

  // 默认进入点选
  activate("click");
})();
