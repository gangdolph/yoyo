'use strict';

import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.161.0/build/three.module.js';
import { EffectComposer } from 'https://cdn.jsdelivr.net/npm/three@0.161.0/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'https://cdn.jsdelivr.net/npm/three@0.161.0/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'https://cdn.jsdelivr.net/npm/three@0.161.0/examples/jsm/postprocessing/UnrealBloomPass.js';
import { RoomEnvironment } from 'https://cdn.jsdelivr.net/npm/three@0.161.0/examples/jsm/environments/RoomEnvironment.js';

const supportsWebGL = (() => {
  try {
    const canvas = document.createElement('canvas');
    return (
      (!!window.WebGL2RenderingContext && !!canvas.getContext('webgl2')) ||
      !!canvas.getContext('webgl') ||
      !!canvas.getContext('experimental-webgl')
    );
  } catch (error) {
    return false;
  }
})();

const reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
const managed = new WeakSet();

const buttons = Array.from(document.querySelectorAll('.cta3d'));
if (!buttons.length) {
  return;
}

buttons.forEach((element) => {
  if (managed.has(element)) {
    return;
  }
  managed.add(element);
  initializeButton(element).catch((error) => {
    console.error('Failed to initialise CTA button', error);
  });
});

function initializeButton(element) {
  let label = (element.dataset.label || '').trim();
  if (!label) {
    label = (element.textContent || '').trim() || 'Action';
    element.dataset.label = label;
  }

  const urlAttr = (element.dataset.url || '').trim();
  const nestedAnchor = element.querySelector('a[href]');
  const destination = urlAttr || (nestedAnchor ? nestedAnchor.getAttribute('href') || '' : '');

  element.setAttribute('role', 'button');
  element.setAttribute('tabindex', '0');
  element.setAttribute('aria-label', label);

  const labelNode = document.createElement('span');
  labelNode.className = 'cta3d__label';
  labelNode.textContent = label;
  labelNode.setAttribute('aria-hidden', 'true');
  element.appendChild(labelNode);

  const navigate = () => {
    if (destination) {
      window.location.href = destination;
      return;
    }
    const fallbackAnchor = element.querySelector('a[href]');
    if (fallbackAnchor) {
      fallbackAnchor.click();
    }
  };

  const controller = new AbortController();
  const { signal } = controller;

  element.addEventListener(
    'click',
    (event) => {
      if (event.defaultPrevented) {
        return;
      }
      const target = event.target;
      if (target instanceof Element && target.closest('a[href]')) {
        return;
      }
      event.preventDefault();
      navigate();
    },
    { signal }
  );

  element.addEventListener(
    'keydown',
    (event) => {
      if (event.defaultPrevented) {
        return;
      }
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        navigate();
      }
    },
    { signal }
  );

  element.addEventListener(
    'pointerdown',
    () => {
      element.classList.add('cta3d--active');
    },
    { signal }
  );

  const clearActive = () => {
    element.classList.remove('cta3d--active');
  };

  element.addEventListener('pointerup', clearActive, { signal });
  element.addEventListener('pointercancel', clearActive, { signal });
  element.addEventListener('lostpointercapture', clearActive, { signal });

  const fallbackStatic = () => {
    element.setAttribute('aria-disabled', 'true');
    element.classList.add('cta3d--fallback');
    labelNode.style.display = 'none';
    if (destination && !element.querySelector('.cta3d-fallback-link')) {
      const fallbackLink = document.createElement('a');
      fallbackLink.className = 'cta3d-fallback-link';
      fallbackLink.href = destination;
      fallbackLink.textContent = label;
      fallbackLink.setAttribute('aria-hidden', 'true');
      element.appendChild(fallbackLink);
    }
  };

  if (!supportsWebGL) {
    fallbackStatic();
    return Promise.resolve();
  }

  return new Promise((resolve) => {
    requestAnimationFrame(() => {
      resolve(setupThreeScene(element, controller, fallbackStatic));
    });
  });
}

function setupThreeScene(element, controller, fallbackStatic) {
  const { signal } = controller;

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setClearColor(0x000000, 0);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.08;

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 50);
  camera.position.set(0, 0.25, 5);

  const group = new THREE.Group();
  group.position.y = -0.1;
  scene.add(group);

  const resources = [];

  let envTarget;
  let pmremGenerator;
  try {
    pmremGenerator = new THREE.PMREMGenerator(renderer);
    pmremGenerator.compileEquirectangularShader();
    const roomEnvironment = new RoomEnvironment();
    envTarget = pmremGenerator.fromScene(roomEnvironment, 0.04);
    scene.environment = envTarget.texture;
    resources.push({ dispose: () => roomEnvironment.dispose() });
  } catch (error) {
    console.warn('Falling back to static CTA due to environment error', error);
    renderer.dispose();
    controller.abort();
    fallbackStatic();
    return;
  }

  const ambientLight = new THREE.AmbientLight(0x14253b, 0.55);
  scene.add(ambientLight);

  const keyLight = new THREE.DirectionalLight(0x7fd4ff, 1.3);
  keyLight.position.set(3, 4.5, 5.5);
  scene.add(keyLight);

  const rimLight = new THREE.DirectionalLight(0xff8bdc, 0.65);
  rimLight.position.set(-4, -2.5, -4);
  scene.add(rimLight);

  const fillLight = new THREE.PointLight(0x35c6ff, 0.9, 6, 2);
  fillLight.position.set(-0.4, 1.8, 1.5);
  scene.add(fillLight);

  const knotGeometry = new THREE.TorusKnotGeometry(0.9, 0.32, 240, 32, 2, 3);
  const knotMaterial = new THREE.MeshPhysicalMaterial({
    color: 0x44dfff,
    emissive: 0x082a3f,
    emissiveIntensity: 0.22,
    metalness: 0.5,
    roughness: 0.14,
    transmission: 0.6,
    thickness: 1.35,
    clearcoat: 1,
    clearcoatRoughness: 0.06,
    sheen: 0.75,
    sheenRoughness: 0.4,
    iridescence: 0.25,
    iridescenceIOR: 1.15,
    reflectivity: 0.75,
    envMapIntensity: 1.3,
  });
  const knot = new THREE.Mesh(knotGeometry, knotMaterial);
  knot.castShadow = false;
  knot.receiveShadow = false;
  group.add(knot);
  resources.push(knotGeometry, knotMaterial);

  const platformGeometry = new THREE.CylinderGeometry(1.25, 1.45, 0.18, 64);
  const platformMaterial = new THREE.MeshStandardMaterial({
    color: 0x0b1629,
    metalness: 0.7,
    roughness: 0.85,
    emissive: 0x041628,
    emissiveIntensity: 0.45,
  });
  const platform = new THREE.Mesh(platformGeometry, platformMaterial);
  platform.position.y = -1.35;
  group.add(platform);
  resources.push(platformGeometry, platformMaterial);

  const haloGeometry = new THREE.RingGeometry(0.95, 1.55, 64);
  const haloMaterial = new THREE.MeshBasicMaterial({
    color: 0x0f3a64,
    opacity: 0.6,
    transparent: true,
  });
  const halo = new THREE.Mesh(haloGeometry, haloMaterial);
  halo.rotation.x = -Math.PI / 2;
  halo.position.y = -1.45;
  group.add(halo);
  resources.push(haloGeometry, haloMaterial);

  const composer = new EffectComposer(renderer);
  const renderPass = new RenderPass(scene, camera);
  composer.addPass(renderPass);

  const bloomPass = new UnrealBloomPass(new THREE.Vector2(1, 1), 1.05, 0.6, 0.6);
  composer.addPass(bloomPass);

  renderer.domElement.style.position = 'absolute';
  renderer.domElement.style.inset = '0';
  renderer.domElement.style.width = '100%';
  renderer.domElement.style.height = '100%';
  renderer.domElement.style.pointerEvents = 'none';
  renderer.domElement.setAttribute('aria-hidden', 'true');

  element.appendChild(renderer.domElement);
  element.setAttribute('aria-disabled', 'false');
  element.classList.add('cta3d--ready');

  const baseRotation = { x: 0.52, y: -0.45 };
  const state = {
    reduceMotion: reduceMotionQuery.matches,
    targetScale: 1,
    currentScale: 1,
    targetRotationX: baseRotation.x,
    targetRotationY: baseRotation.y,
    rotationX: baseRotation.x,
    rotationY: baseRotation.y,
  };

  const updateReducedMotion = (matches) => {
    state.reduceMotion = matches;
    if (matches) {
      state.targetScale = 1;
      bloomPass.strength = 0.45;
      element.classList.add('cta3d--reduced-motion');
    } else {
      bloomPass.strength = 1.05;
      element.classList.remove('cta3d--reduced-motion');
    }
  };
  updateReducedMotion(state.reduceMotion);

  reduceMotionQuery.addEventListener(
    'change',
    (event) => {
      updateReducedMotion(event.matches);
    },
    { signal }
  );

  const pointerState = { inside: false };

  const handlePointerEnter = () => {
    pointerState.inside = true;
    element.classList.add('cta3d--active');
    if (!state.reduceMotion) {
      state.targetScale = 1.08;
    }
  };

  const resetPointer = () => {
    pointerState.inside = false;
    element.classList.remove('cta3d--active');
    state.targetScale = 1;
    state.targetRotationX = baseRotation.x;
    state.targetRotationY = baseRotation.y;
  };

  const handlePointerMove = (event) => {
    if (!pointerState.inside || state.reduceMotion) {
      return;
    }
    const rect = element.getBoundingClientRect();
    if (!rect.width || !rect.height) {
      return;
    }
    const normX = (event.clientX - rect.left) / rect.width;
    const normY = (event.clientY - rect.top) / rect.height;
    const targetX = baseRotation.x + (0.5 - normY) * 0.5;
    const targetY = baseRotation.y + (normX - 0.5) * 0.8;
    state.targetRotationX = THREE.MathUtils.clamp(targetX, baseRotation.x - 0.4, baseRotation.x + 0.4);
    state.targetRotationY = THREE.MathUtils.clamp(targetY, baseRotation.y - 0.6, baseRotation.y + 0.6);
  };

  element.addEventListener('pointerenter', handlePointerEnter, { signal });
  element.addEventListener('pointerleave', resetPointer, { signal });
  element.addEventListener('pointermove', handlePointerMove, { signal, passive: true });

  element.addEventListener(
    'focus',
    () => {
      element.classList.add('cta3d--active');
      if (!state.reduceMotion) {
        state.targetScale = 1.08;
      }
    },
    { signal }
  );

  element.addEventListener(
    'blur',
    () => {
      element.classList.remove('cta3d--active');
      state.targetScale = 1;
      state.targetRotationX = baseRotation.x;
      state.targetRotationY = baseRotation.y;
    },
    { signal }
  );

  const resizeRenderer = () => {
    const rect = element.getBoundingClientRect();
    const width = Math.max(rect.width, 1);
    const height = Math.max(rect.height, 1);
    renderer.setSize(width, height, false);
    composer.setSize(width, height);
    bloomPass.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
  };

  let resizeObserver;
  if ('ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(() => resizeRenderer());
    resizeObserver.observe(element);
  } else {
    window.addEventListener('resize', resizeRenderer, { signal });
  }
  resizeRenderer();

  const clock = new THREE.Clock();
  let rafId = 0;
  let running = false;

  const renderFrame = () => {
    if (!running) {
      return;
    }
    rafId = window.requestAnimationFrame(renderFrame);
    const delta = clock.getDelta();

    if (!state.reduceMotion) {
      knot.rotation.y += delta * 0.6;
      knot.rotation.x += delta * 0.35;
      halo.material.opacity = 0.5 + Math.sin(clock.elapsedTime * 1.5) * 0.1;
    }

    state.rotationX += (state.targetRotationX - state.rotationX) * 0.08;
    state.rotationY += (state.targetRotationY - state.rotationY) * 0.08;
    group.rotation.x = state.rotationX;
    group.rotation.y = state.rotationY;

    state.currentScale += (state.targetScale - state.currentScale) * 0.08;
    group.scale.setScalar(state.currentScale);

    composer.render();
  };

  const start = () => {
    if (running) {
      return;
    }
    running = true;
    clock.start();
    rafId = window.requestAnimationFrame(renderFrame);
  };

  const stop = () => {
    if (!running) {
      return;
    }
    running = false;
    window.cancelAnimationFrame(rafId);
  };

  let intersectionObserver;
  if ('IntersectionObserver' in window) {
    intersectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.target !== element) {
            return;
          }
          if (entry.isIntersecting) {
            start();
          } else {
            stop();
          }
        });
      },
      { threshold: 0.15 }
    );

    intersectionObserver.observe(element);
  } else {
    start();
  }

  const mutationObserver = new MutationObserver(() => {
    if (!element.isConnected) {
      cleanup();
    }
  });
  mutationObserver.observe(document.body, { childList: true, subtree: true });

  let cleaned = false;
  const cleanup = () => {
    if (cleaned) {
      return;
    }
    cleaned = true;

    if (!signal.aborted) {
      controller.abort();
    }

    stop();
    if (intersectionObserver) {
      intersectionObserver.disconnect();
    }
    if (resizeObserver) {
      resizeObserver.disconnect();
    }
    mutationObserver.disconnect();

    element.classList.remove('cta3d--ready', 'cta3d--active', 'cta3d--fallback');

    if (renderer.domElement.parentElement === element) {
      element.removeChild(renderer.domElement);
    }

    resources.forEach((resource) => {
      if (resource && typeof resource.dispose === 'function') {
        resource.dispose();
      }
    });

    if (envTarget) {
      envTarget.dispose();
    }
    if (pmremGenerator) {
      pmremGenerator.dispose();
    }
    if (composer.dispose) {
      composer.dispose();
    } else {
      if (composer.renderTarget1) {
        composer.renderTarget1.dispose();
      }
      if (composer.renderTarget2) {
        composer.renderTarget2.dispose();
      }
    }
    renderer.dispose();
    if (renderer.forceContextLoss) {
      renderer.forceContextLoss();
    }
  };

  signal.addEventListener('abort', cleanup);

  start();
}
