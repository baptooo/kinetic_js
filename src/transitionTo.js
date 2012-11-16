var transitionTo = function(config) {
    if(!this.transAnim) {
        this.transAnim = new Kinetic.Animation();
    }
    /*
     * create new transition
     */
    var node = this.nodeType === 'Stage' ? this : this.getLayer();
    var that = this;
    var trans = new Kinetic.Transition(this, config);
    var start = new Date().getTime();

    this.transAnim.func = function(frame) {
    	var frameTime = frame.lastTime - start;
    	config.step && config.step.apply(config, Array.prototype.slice.call(arguments).concat(Math.min(1, [frameTime / (config.duration * 1000)])));
        trans._onEnterFrame();
    };
    this.transAnim.node = node;

    // subscribe to onFinished for first tween
    trans.onFinished = function() {
        // remove animation
        that.transAnim.stop();
        
        // callback
        if(config.callback) {
            config.callback();
        }
    };
    // auto start
    trans.start();
    this.transAnim.start();
    return trans;
};

// Ugly...
for(var i in Kinetic) {
	if(!Kinetic.hasOwnProperty(i) || !Kinetic[i].prototype) continue;
	Kinetic[i].prototype.transitionTo = transitionTo;
};