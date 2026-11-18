/* bg-3d.js - Local Three.js WebGL 3D Immunology Backdrop.
   Isolated in its own file, no CDN, respects prefers-reduced-motion. */
(function () {
  var canvas = document.getElementById('bg-canvas');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!canvas || !window.THREE || reduceMotion) return;

  var renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
  } catch (e) {
    return; // Graceful fallback if WebGL is unavailable
  }

  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
  renderer.setSize(window.innerWidth, window.innerHeight, false);

  var scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x0f111a, 0.022);

  var camera = new THREE.PerspectiveCamera(52, window.innerWidth / window.innerHeight, 0.1, 100);
  camera.position.set(0, 0, 22);

  // Ambient + indigo/mint point lights for subtle 3D depth
  scene.add(new THREE.AmbientLight(0x818cf8, 0.55));
  var lightIndigo = new THREE.PointLight(0x6366f1, 1.8, 60);
  lightIndigo.position.set(-12, 10, 14);
  scene.add(lightIndigo);

  var lightMint = new THREE.PointLight(0x2ee59d, 1.4, 60);
  lightMint.position.set(14, -8, 12);
  scene.add(lightMint);

  // 1. 3D DNA Double-Helix Strand (offset subtly to the background)
  var helixGroup = new THREE.Group();
  var sphereGeo = new THREE.SphereGeometry(0.18, 12, 12);
  var matIndigo = new THREE.MeshStandardMaterial({ color: 0x6366f1, emissive: 0x312e81, roughness: 0.3, metalness: 0.2 });
  var matMint   = new THREE.MeshStandardMaterial({ color: 0x2ee59d, emissive: 0x065f46, roughness: 0.3, metalness: 0.2 });
  var rungMat   = new THREE.LineBasicMaterial({ color: 0x4f46e5, transparent: true, opacity: 0.35 });

  var pairs = 26;
  var radius = 2.6;
  var stepY = 0.72;
  for (var i = 0; i < pairs; i++) {
    var angle = i * 0.42;
    var y = (i - pairs / 2) * stepY;
    var x1 = Math.cos(angle) * radius, z1 = Math.sin(angle) * radius;
    var x2 = Math.cos(angle + Math.PI) * radius, z2 = Math.sin(angle + Math.PI) * radius;

    var s1 = new THREE.Mesh(sphereGeo, matIndigo);
    s1.position.set(x1, y, z1);
    helixGroup.add(s1);

    var s2 = new THREE.Mesh(sphereGeo, matMint);
    s2.position.set(x2, y, z2);
    helixGroup.add(s2);

    var lineGeo = new THREE.BufferGeometry().setFromPoints([
      new THREE.Vector3(x1, y, z1),
      new THREE.Vector3(x2, y, z2)
    ]);
    helixGroup.add(new THREE.Line(lineGeo, rungMat));
  }
  helixGroup.position.set(9.5, 0, -6);
  helixGroup.rotation.z = 0.28;
  scene.add(helixGroup);

  // 2. Wireframe Icosahedral "Immunity Shield" Cells
  var cells = [];
  var cellConfigs = [
    { size: 2.2, color: 0x6366f1, pos: [-11, 5.5, -5], speed: 0.003 },
    { size: 1.5, color: 0x2ee59d, pos: [-8, -6.5, -3], speed: -0.004 },
    { size: 1.8, color: 0x818cf8, pos: [12, -7, -8], speed: 0.0025 },
    { size: 1.1, color: 0x2ee59d, pos: [3, 8, -10], speed: -0.0035 }
  ];
  cellConfigs.forEach(function (cfg, idx) {
    var geo = new THREE.IcosahedronGeometry(cfg.size, 1);
    var wire = new THREE.WireframeGeometry(geo);
    var mat = new THREE.LineBasicMaterial({ color: cfg.color, transparent: true, opacity: 0.22 });
    var mesh = new THREE.LineSegments(wire, mat);
    mesh.position.set(cfg.pos[0], cfg.pos[1], cfg.pos[2]);
    mesh.userData = { speed: cfg.speed, baseY: cfg.pos[1], phase: idx * 1.4 };
    scene.add(mesh);
    cells.push(mesh);
  });

  // 3. 3D Depth Particle Field (Indigo & Mint)
  var pCount = 220;
  var pPos = new Float32Array(pCount * 3);
  var pCol = new Float32Array(pCount * 3);
  var cIndigo = new THREE.Color(0x6366f1);
  var cMint   = new THREE.Color(0x2ee59d);

  for (var k = 0; k < pCount; k++) {
    pPos[k * 3]     = (Math.random() - 0.5) * 46;
    pPos[k * 3 + 1] = (Math.random() - 0.5) * 32;
    pPos[k * 3 + 2] = (Math.random() - 0.5) * 26 - 4;
    var c = (k % 3 === 0) ? cMint : cIndigo;
    pCol[k * 3] = c.r; pCol[k * 3 + 1] = c.g; pCol[k * 3 + 2] = c.b;
  }
  var pGeo = new THREE.BufferGeometry();
  pGeo.setAttribute('position', new THREE.BufferAttribute(pPos, 3));
  pGeo.setAttribute('color', new THREE.BufferAttribute(pCol, 3));
  var pMat = new THREE.PointsMaterial({ size: 0.14, vertexColors: true, transparent: true, opacity: 0.65 });
  var points = new THREE.Points(pGeo, pMat);
  scene.add(points);

  // Subtle pointer & scroll parallax
  var targetX = 0, targetY = 0;
  window.addEventListener('pointermove', function (e) {
    targetX = ((e.clientX / window.innerWidth) - 0.5) * 1.6;
    targetY = ((e.clientY / window.innerHeight) - 0.5) * 1.2;
  }, { passive: true });

  window.addEventListener('resize', function () {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight, false);
  });

  var clock = new THREE.Clock();
  function animate() {
    var t = clock.getElapsedTime();
    var scrollOffset = (window.scrollY || 0) * 0.0025;

    helixGroup.rotation.y = t * 0.22;
    helixGroup.position.y = Math.sin(t * 0.4) * 0.6 - scrollOffset * 0.6;

    for (var i = 0; i < cells.length; i++) {
      var cell = cells[i];
      cell.rotation.x += cell.userData.speed;
      cell.rotation.y += cell.userData.speed * 1.3;
      cell.position.y = cell.userData.baseY + Math.sin(t * 0.6 + cell.userData.phase) * 0.45;
    }

    points.rotation.y = t * 0.02;
    camera.position.x += (targetX - camera.position.x) * 0.03;
    camera.position.y += (-targetY - scrollOffset - camera.position.y) * 0.03;
    camera.lookAt(0, -scrollOffset * 0.4, 0);

    renderer.render(scene, camera);
    requestAnimationFrame(animate);
  }
  requestAnimationFrame(animate);
})();
